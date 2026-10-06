<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AssetDepreciation;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\FixedAsset;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\FixedAssetService;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The fixed asset register, depreciation runs and disposals for the React and Blade screens and the API.
 */
class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $assets) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['as_of' => ['nullable', 'date'], 'status' => ['nullable', 'in:active,disposed']]);
        $register = $this->assets->register($filters['as_of'] ?? null, $filters['status'] ?? null);
        $reconcile = $this->assets->reconcile($filters['as_of'] ?? null);

        return $request->expectsJson()
            ? response()->json(['data' => [...$register, 'reconcile' => $reconcile]])
            : $this->render('index', ['register' => $register, 'reconcile' => $reconcile, 'filters' => ['as_of' => $register['as_of'], 'status' => $filters['status'] ?? '']]);
    }

    public function create(): Response|View
    {
        return $this->render('form', $this->formProps(null));
    }

    public function edit(FixedAsset $asset): Response|View
    {
        abort_unless($asset->status === 'active', 403, 'A disposed asset cannot be changed.');

        return $this->render('form', $this->formProps($asset));
    }

    public function show(Request $request, FixedAsset $asset): Response|View|JsonResponse
    {
        $history = AssetDepreciation::query()->where('fixed_asset_id', $asset->id)->orderBy('period_month')
            ->get(['id', 'period_month', 'amount', 'journal_entry_id'])->map(fn (AssetDepreciation $row): array => ['month' => $row->period_month->format('Y-m'), 'amount' => $row->amount, 'journal_entry_id' => $row->journal_entry_id])->values();
        $props = ['asset' => $this->present($asset), 'history' => $history, 'accounts' => $this->accountOptions(), 'today' => now()->toDateString()];

        return $request->expectsJson() ? response()->json(['data' => ['asset' => $props['asset'], 'depreciation' => $history]]) : $this->render('show', $props);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $asset = $this->assets->create($this->assets->validate($request->all()));

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($asset)], 201)
                : to_route($this->routeName('fixed-assets.show'), $asset)->with('success', 'Asset registered.');
        });
    }

    public function update(Request $request, FixedAsset $asset): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $asset) {
            $asset = $this->assets->update($asset, $this->assets->validate($request->all(), $asset));

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($asset)])
                : to_route($this->routeName('fixed-assets.show'), $asset)->with('success', 'Asset updated.');
        });
    }

    public function destroy(Request $request, FixedAsset $asset): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $asset) {
            $this->assets->delete($asset);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('fixed-assets.index'))->with('success', 'Asset deleted.');
        });
    }

    public function depreciation(Request $request): Response|View|JsonResponse
    {
        $data = $request->validate(['up_to' => ['nullable', 'date']]);
        $upTo = $data['up_to'] ?? Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $preview = $this->assets->preview($upTo);

        return $request->expectsJson() ? response()->json(['data' => $preview]) : $this->render('depreciation', ['preview' => $preview, 'upTo' => Carbon::parse($upTo)->toDateString()]);
    }

    public function runDepreciation(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['up_to' => ['required', 'date']]);

        return $this->guard($request, function () use ($request, $data) {
            $result = $this->assets->run($data['up_to']);
            $message = $result['months'] === 0 ? 'Nothing to depreciate.' : "Depreciation booked for {$result['months']} month(s): {$result['total']}.";

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'data' => $result])
                : to_route($this->routeName('fixed-assets.depreciation'), ['up_to' => $data['up_to']])->with('success', $message);
        });
    }

    public function dispose(Request $request, FixedAsset $asset): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $asset) {
            $asset = $this->assets->dispose($asset, $request->all());

            return $request->expectsJson()
                ? response()->json(['message' => 'Asset disposed.', 'data' => $this->present($asset)])
                : to_route($this->routeName('fixed-assets.show'), $asset)->with('success', 'Asset disposed.');
        });
    }

    public function export(Request $request, string $format, AccountingReportExporter $exporter): HttpResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $filters = $request->validate(['as_of' => ['nullable', 'date'], 'status' => ['nullable', 'in:active,disposed']]);
        $register = $this->assets->register($filters['as_of'] ?? null, $filters['status'] ?? null);
        $rows = collect($register['rows'])->map(fn (array $row): array => collect($row)->only(['code', 'name', 'category', 'acquisition_date', 'status', 'cost', 'accumulated', 'book_value'])->all());

        return $exporter->download($rows, 'fixed-asset-register', $format, ['title' => 'Fixed asset register', 'filters' => ['As of' => $register['as_of']]]);
    }

    /**
     * @param  \Closure(): (RedirectResponse|JsonResponse)  $action
     */
    private function guard(Request $request, \Closure $action): RedirectResponse|JsonResponse
    {
        try {
            return $action();
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::fixed-assets.'.$page, $props)
            : Inertia::render('accounting/fixed-assets/'.$page, $props);
    }

    /**
     * @return list<array{id: int, account_code: string, account_name: string, type: string}>
     */
    private function accountOptions(): array
    {
        return ChartOfAccount::query()->with('accountType:id,code')->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type_id'])
            ->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name, 'type' => $account->accountType->code])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?FixedAsset $asset): array
    {
        return ['asset' => $asset ? $this->present($asset) : null, 'accounts' => $this->accountOptions(), 'methods' => FixedAsset::METHODS, 'today' => now()->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FixedAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'code' => $asset->code,
            'name' => $asset->name,
            'category' => $asset->category,
            'description' => $asset->description,
            'acquisition_date' => $asset->acquisition_date->toDateString(),
            'in_service_date' => $asset->in_service_date->toDateString(),
            'cost' => $asset->cost,
            'salvage_value' => $asset->salvage_value,
            'useful_life_months' => $asset->useful_life_months,
            'method' => $asset->method,
            'declining_rate' => $asset->declining_rate,
            'asset_account_id' => $asset->asset_account_id,
            'accumulated_account_id' => $asset->accumulated_account_id,
            'expense_account_id' => $asset->expense_account_id,
            'status' => $asset->status,
            'accumulated_depreciation' => $asset->accumulated_depreciation,
            'book_value' => Money::fromCents(Money::toCents($asset->cost) - Money::toCents($asset->accumulated_depreciation)),
            'acquisition_entry_id' => $asset->acquisition_entry_id,
            'locked' => $asset->acquisition_entry_id !== null || $asset->depreciations()->exists(),
            'disposed_at' => $asset->disposed_at?->toDateString(),
            'disposal_proceeds' => $asset->disposal_proceeds,
            'disposal_gain_loss' => $asset->disposal_gain_loss,
            'disposal_entry_id' => $asset->disposal_entry_id,
        ];
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
