<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ChartRestructureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renumber and merge accounts (React and Blade page, and the API). The page previews with GET parameters
 * (renumber_code / merge_target) and applies with POST.
 */
class ChartRestructureController extends Controller
{
    public function __construct(private readonly ChartRestructureService $restructure) {}

    public function show(Request $request, ChartOfAccount $chartOfAccount): Response|View
    {
        $renumber = null;
        $merge = null;
        $metadata = $chartOfAccount->getAttribute('metadata');
        $code = trim((string) $request->query('renumber_code', ''));
        $withChildren = $request->query('with_children', '1') !== '0';

        if ($code !== '') {
            try {
                $renumber = ['code' => $code, 'with_children' => $withChildren, 'plan' => $this->restructure->renumberPlan($chartOfAccount, $code, $withChildren), 'error' => null];
            } catch (AccountingException $exception) {
                $renumber = ['code' => $code, 'with_children' => $withChildren, 'plan' => [], 'error' => $exception->getMessage()];
            }
        }

        if ($request->filled('merge_target')) {
            $target = ChartOfAccount::query()->find((int) $request->query('merge_target'));
            $merge = $target ? $this->restructure->mergePlan($chartOfAccount, $target) : null;
        }

        $props = [
            'account' => [
                'id' => $chartOfAccount->id,
                'account_code' => $chartOfAccount->account_code,
                'account_name' => $chartOfAccount->account_name,
                'is_group' => (bool) $chartOfAccount->is_group,
                'is_active' => (bool) $chartOfAccount->is_active,
                'merged_into' => is_array($metadata) ? ($metadata['merged_into'] ?? null) : null,
            ],
            'renumber' => $renumber,
            'merge' => $merge,
            'targets' => ChartOfAccount::query()
                ->where('is_active', true)->whereKeyNot($chartOfAccount->id)
                ->where('account_type_id', $chartOfAccount->account_type_id)->where('is_group', $chartOfAccount->is_group)
                ->orderBy('account_code')->get(['id', 'account_code', 'account_name'])
                ->map(fn (ChartOfAccount $account) => ['id' => $account->id, 'label' => "{$account->account_code} {$account->account_name}"])->values(),
            'today' => now()->toDateString(),
        ];

        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::chart-of-accounts.restructure', $props)
            : Inertia::render('accounting/chart-of-accounts/restructure', $props);
    }

    public function renumber(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'account_code' => ['required', 'string', 'max:30'],
            'with_children' => ['nullable', 'boolean'],
        ]);

        try {
            $plan = $this->restructure->renumber($chartOfAccount, $data['account_code'], $request->boolean('with_children', true));
        } catch (AccountingException $exception) {
            return $request->expectsJson() ? throw $exception : back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Account renumbered.', 'data' => ['map' => $plan]]);
        }

        $count = count($plan);

        return to_route($this->routeName('chart-of-accounts.index'))
            ->with('success', $count === 1 ? "Account renumbered to {$plan[array_key_first($plan)]}." : "{$count} accounts renumbered.");
    }

    public function merge(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'target_account_id' => ['required_without:target_code', 'nullable', 'integer'],
            'target_code' => ['required_without:target_account_id', 'nullable', 'string'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $target = isset($data['target_account_id'])
            ? ChartOfAccount::query()->find($data['target_account_id'])
            : ChartOfAccount::query()->where('account_code', $data['target_code'])->first();
        abort_if($target === null, 422, 'The account to merge into does not exist.');

        try {
            $result = $this->restructure->merge($chartOfAccount, $target, $data['date'] ?? null, $data['description'] ?? null);
        } catch (AccountingException $exception) {
            return $request->expectsJson() ? throw $exception : back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => "Account merged into {$target->account_code}.", 'data' => $result]);
        }

        return to_route($this->routeName('chart-of-accounts.index'))->with('success', "{$chartOfAccount->account_code} merged into {$target->account_code}"
            .($result['voucher_number'] ? " (balance moved by {$result['voucher_number']})." : '.'));
    }

    /**
     * API previews.
     */
    public function renumberPreview(Request $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        $data = $request->validate(['account_code' => ['required', 'string', 'max:30'], 'with_children' => ['nullable', 'boolean']]);

        return response()->json(['data' => ['map' => $this->restructure->renumberPlan($chartOfAccount, $data['account_code'], $request->boolean('with_children', true))]]);
    }

    public function mergePreview(Request $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        $data = $request->validate(['target_account_id' => ['required', 'integer']]);
        $target = ChartOfAccount::query()->find($data['target_account_id']);
        abort_if($target === null, 404);

        return response()->json(['data' => $this->restructure->mergePlan($chartOfAccount, $target)]);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
