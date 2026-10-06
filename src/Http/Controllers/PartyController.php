<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\PartyLedgerService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
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
 * Customers and suppliers, their account (open items, statement) and the ageing report, for the React and Blade
 * screens and the API (JSON when the request expects it).
 */
class PartyController extends Controller
{
    public function __construct(private readonly PartyService $parties, private readonly PartyLedgerService $ledger) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['type' => ['nullable', 'in:customer,supplier,both'], 'search' => ['nullable', 'string', 'max:100'], 'active' => ['nullable', 'boolean']]);
        $parties = Party::query()
            ->when($filters['type'] ?? null, fn ($query, $type) => $type === 'both' ? $query->where('type', 'both') : $query->whereIn('type', [$type, 'both']))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when(isset($filters['active']), fn ($query) => $query->where('is_active', (bool) $filters['active']))
            ->orderBy('name')->get()->map(fn (Party $party): array => $this->present($party))->values();

        return $request->expectsJson() ? response()->json(['data' => $parties]) : $this->render('index', ['parties' => $parties, 'filters' => ['type' => $filters['type'] ?? null, 'search' => $filters['search'] ?? null]]);
    }

    public function create(): Response|View
    {
        return $this->render('form', $this->formProps(null));
    }

    public function edit(Party $party): Response|View
    {
        return $this->render('form', $this->formProps($party));
    }

    public function show(Request $request, Party $party): Response|View|JsonResponse
    {
        $side = $this->side($request, $party);
        $asOf = Carbon::parse($request->input('as_of', now()))->toDateString();
        $open = $this->ledger->openItems($party, $side, $asOf);

        if ($request->expectsJson()) {
            return response()->json(['data' => [...$this->present($party), 'side' => $side, 'balance' => $open['balance'], 'open_items' => $open['items'], 'unapplied' => $open['credits']]]);
        }

        $from = Carbon::parse($request->input('date_from', now()->startOfYear()))->toDateString();
        $to = Carbon::parse($request->input('date_to', now()))->toDateString();

        return $this->render('show', [
            'party' => $this->present($party),
            'side' => $side,
            'openItems' => $open,
            'statement' => $this->ledger->statement($party, $side, $from, $to),
            'range' => ['date_from' => $from, 'date_to' => $to],
        ]);
    }

    public function statement(Request $request, Party $party): JsonResponse
    {
        $side = $this->side($request, $party);
        $from = Carbon::parse($request->input('date_from', now()->startOfYear()))->toDateString();
        $to = Carbon::parse($request->input('date_to', now()))->toDateString();

        return response()->json(['data' => $this->ledger->statement($party, $side, $from, $to)]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $party = $this->parties->create($this->parties->validate($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $this->present($party)], 201) : to_route($this->routeName('parties.show'), $party)->with('success', 'Saved.');
    }

    public function update(Request $request, Party $party): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $party) {
            $party = $this->parties->update($party, $this->parties->validate($request->all(), $party));

            return $request->expectsJson() ? response()->json(['data' => $this->present($party)]) : to_route($this->routeName('parties.show'), $party)->with('success', 'Saved.');
        });
    }

    public function destroy(Request $request, Party $party): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $party) {
            $this->parties->delete($party);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('parties.index'))->with('success', 'Deleted.');
        });
    }

    public function aging(Request $request): Response|View|JsonResponse
    {
        [$side, $asOf] = $this->agingFilters($request);
        $report = $this->ledger->aging($side, $asOf);
        $reconciliation = $this->ledger->reconcile($side, $asOf);

        return $request->expectsJson()
            ? response()->json(['data' => [...$report, 'reconciliation' => $reconciliation]])
            : $this->render('aging', ['report' => $report, 'reconciliation' => $reconciliation]);
    }

    public function agingExport(Request $request, string $format, AccountingReportExporter $exporter): HttpResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        [$side, $asOf] = $this->agingFilters($request);
        $report = $this->ledger->aging($side, $asOf);
        $rows = collect($report['rows'])->map(fn (array $row): array => [
            'code' => $row['code'], 'name' => $row['name'], 'not_due' => $row['not_due'], '1_30_days' => $row['days_1_30'], '31_60_days' => $row['days_31_60'],
            '61_90_days' => $row['days_61_90'], 'over_90_days' => $row['over_90'], 'unapplied' => $row['unapplied'], 'total' => $row['total'],
        ]);

        return $exporter->download($rows, "aged-{$side}-{$asOf}", $format, ['title' => $side === 'receivable' ? 'Aged receivables by customer' : 'Aged payables by supplier', 'filters' => ['As of' => $asOf, 'Total' => $report['totals']['total']]]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function agingFilters(Request $request): array
    {
        $data = $request->validate(['side' => ['nullable', 'in:receivable,payable'], 'as_of' => ['nullable', 'date']]);

        return [$data['side'] ?? 'receivable', Carbon::parse($data['as_of'] ?? now())->toDateString()];
    }

    private function side(Request $request, Party $party): string
    {
        $side = (string) $request->input('side', $party->isCustomer() ? 'receivable' : 'payable');

        return $side === 'payable' ? 'payable' : 'receivable';
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Party $party): array
    {
        return [
            'party' => $party ? $this->present($party) : null,
            'receivableAccounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->whereHas('accountType', fn ($query) => $query->where('code', 'ASSET'))->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'payableAccounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->whereHas('accountType', fn ($query) => $query->where('code', 'LIABILITY'))->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Party $party): array
    {
        return [
            'id' => $party->id,
            'type' => $party->type,
            'code' => $party->code,
            'name' => $party->name,
            'email' => $party->email,
            'phone' => $party->phone,
            'address' => $party->address,
            'tax_number' => $party->tax_number,
            'payment_terms_days' => $party->payment_terms_days,
            'credit_limit' => $party->credit_limit,
            'receivable_account_id' => $party->receivable_account_id,
            'payable_account_id' => $party->payable_account_id,
            'is_active' => $party->is_active,
            'notes' => $party->notes,
        ];
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
            ? view('accounting::parties.'.$page, $props)
            : Inertia::render('accounting/parties/'.$page, $props);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
