<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxReturn;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\TaxService;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Tax calculation, taxed documents and tax returns for the React and Blade screens and the API.
 */
class TaxController extends Controller
{
    private const TYPE_LABELS = [
        'sale' => 'Sale (invoice)',
        'sale_return' => 'Sales return (credit note)',
        'purchase' => 'Purchase (bill)',
        'purchase_return' => 'Purchase return (debit note)',
        'withholding_payment' => 'Payment with tax withheld',
        'withholding_receipt' => 'Receipt with tax withheld by the customer',
    ];

    public function __construct(private readonly TaxService $tax) {}

    public function index(): Response|View
    {
        $today = now()->toDateString();
        $codes = TaxCode::query()->with('taxAccount:id,account_code,account_name')->orderBy('code')->get()->map(fn (TaxCode $code): array => [
            'id' => $code->id,
            'code' => $code->code,
            'name' => $code->name,
            'kind' => $code->kind,
            'rate' => $this->tax->rateOn($code, $today),
            'tax_account' => $code->taxAccount ? $code->taxAccount->account_code.' '.$code->taxAccount->account_name : null,
            'jurisdiction' => $code->jurisdiction,
            'is_active' => $code->is_active,
        ])->values();

        return $this->render('index', [
            'taxCodes' => $codes,
            'returns' => TaxReturn::query()->with('journalEntry:id,voucher_number')->orderByDesc('period_to')->limit(100)->get()->map(fn (TaxReturn $return): array => $this->presentReturn($return))->values(),
            'accounts' => $this->accounts(['LIABILITY', 'ASSET']),
            'today' => $today,
            'monthStart' => now()->startOfMonth()->toDateString(),
            'monthEnd' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_code_id' => ['required', 'integer', CompanyRule::exists('accounting_tax_codes', 'id')],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999999'],
            'inclusive' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date'],
        ]);

        return $this->guardJson(fn () => response()->json(['data' => $this->tax->calculate(TaxCode::query()->findOrFail($data['tax_code_id']), (string) $data['amount'], (bool) ($data['inclusive'] ?? false), Carbon::parse($data['date'] ?? now())->toDateString())]));
    }

    public function createEntry(): Response|View
    {
        return $this->render('entry', [
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get()->map(fn (TaxCode $code): array => ['id' => $code->id, 'code' => $code->code, 'name' => $code->name, 'kind' => $code->kind, 'rate' => $this->tax->rateOn($code, now()->toDateString())])->values(),
            'accounts' => $this->accounts(['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE']),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'types' => collect(self::TYPE_LABELS)->map(fn (string $label, string $key): array => ['value' => $key, 'label' => $label])->values(),
            'today' => now()->toDateString(),
        ]);
    }

    public function storeEntry(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(TaxService::TYPES)],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999999'],
            'tax_inclusive' => ['nullable', 'boolean'],
            'tax_code_id' => ['required', 'integer', CompanyRule::exists('accounting_tax_codes', 'id')],
            'account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
            'counter_account_id' => ['required', 'integer', 'different:account_id', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
            'cost_center_id' => ['nullable', 'integer', CompanyRule::exists('accounting_cost_centers', 'id')],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'auto_post' => ['nullable', 'boolean'],
        ]);

        return $this->guard($request, function () use ($request, $data) {
            $entry = $this->tax->entry([...$data, 'amount' => (string) $data['amount'], 'tax_inclusive' => (bool) ($data['tax_inclusive'] ?? false), 'auto_post' => (bool) ($data['auto_post'] ?? false)]);

            $postError = $this->tax->postError();

            return $request->expectsJson()
                ? response()->json(['data' => ['id' => $entry->id, 'status' => $entry->status, 'voucher_number' => $entry->getAttribute('voucher_number'), 'post_error' => $postError]], 201)
                : to_route($this->routeName('journal-entries.show'), $entry)->with('success', $entry->status === 'posted' ? 'Entry posted.' : 'Entry saved as a draft'.($postError ? ": {$postError}" : '.'));
        });
    }

    public function returns(Request $request): JsonResponse
    {
        $returns = TaxReturn::query()->with('journalEntry:id,voucher_number')->orderByDesc('period_to')->get()->map(fn (TaxReturn $return): array => $this->presentReturn($return))->values();

        return response()->json(['data' => $returns]);
    }

    public function showReturn(TaxReturn $taxReturn): JsonResponse
    {
        return response()->json(['data' => $this->presentReturn($taxReturn->load('journalEntry:id,voucher_number'))]);
    }

    public function report(Request $request): Response|View|JsonResponse
    {
        [$from, $to, $codeId] = $this->range($request);
        $report = $this->tax->report($from, $to, $codeId);

        if ($request->expectsJson()) {
            return response()->json(['data' => $report]);
        }

        return $this->render('report', [
            'report' => $report,
            'detail' => $this->tax->detail($from, $to, $codeId)->map(fn (\stdClass $row): array => [
                'journal_entry_id' => (int) $row->journal_entry_id,
                'voucher_number' => $row->voucher_number,
                'entry_date' => Carbon::parse($row->entry_date)->toDateString(),
                'reference' => $row->reference,
                'tax_code' => $row->tax_code,
                'role' => $row->tax_role,
                'rate' => $row->tax_rate === null ? null : (string) $row->tax_rate,
                'amount' => Money::fromCents(in_array($row->kind, TaxCode::CREDIT_KINDS, true) ? Money::toCents((string) $row->base_credit) - Money::toCents((string) $row->base_debit) : Money::toCents((string) $row->base_debit) - Money::toCents((string) $row->base_credit)),
            ])->values(),
            'filters' => ['date_from' => $from, 'date_to' => $to, 'tax_code_id' => $codeId],
            'taxCodes' => TaxCode::query()->orderBy('code')->get(['id', 'code', 'name']),
            'accounts' => $this->accounts(['LIABILITY', 'ASSET']),
        ]);
    }

    public function export(Request $request, string $format, AccountingReportExporter $exporter): HttpResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        [$from, $to, $codeId] = $this->range($request);
        $report = $this->tax->report($from, $to, $codeId);
        $rows = collect($report['rows'])->map(fn (array $row): array => ['tax_code' => $row['code'], 'name' => $row['name'], 'kind' => $row['kind'], 'taxable_base' => $row['base'], 'tax' => $row['tax'], 'documents' => (string) $row['documents']]);

        return $exporter->download($rows, "tax-report-{$from}-{$to}", $format, ['title' => 'Tax report', 'filters' => ['From' => $from, 'To' => $to, 'Output tax' => $report['totals']['output_tax'], 'Input tax' => $report['totals']['input_tax'], 'Net payable' => $report['totals']['net_payable']]]);
    }

    public function fileReturn(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'payable_account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->guard($request, function () use ($request, $data) {
            $return = $this->tax->file(Carbon::parse($data['period_from'])->toDateString(), Carbon::parse($data['period_to'])->toDateString(), (int) $data['payable_account_id'], $data['reference'] ?? null, $data['notes'] ?? null);

            return $request->expectsJson()
                ? response()->json(['data' => $this->presentReturn($return->load('journalEntry:id,voucher_number'))], 201)
                : to_route($this->routeName('tax.index'))->with('success', 'Return filed: the period is settled.');
        });
    }

    public function destroyReturn(Request $request, TaxReturn $taxReturn): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $taxReturn) {
            $this->tax->void($taxReturn);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('tax.index'))->with('success', 'Return voided: its entry was reversed.');
        });
    }

    /**
     * @return array{0: string, 1: string, 2: int|null}
     */
    private function range(Request $request): array
    {
        $data = $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'tax_code_id' => ['nullable', 'integer']]);

        return [
            Carbon::parse($data['date_from'] ?? now()->startOfMonth())->toDateString(),
            Carbon::parse($data['date_to'] ?? now()->endOfMonth())->toDateString(),
            isset($data['tax_code_id']) ? (int) $data['tax_code_id'] : null,
        ];
    }

    /**
     * @param  list<string>  $types
     * @return Collection<int, array{id: int, account_code: string, account_name: string}>
     */
    private function accounts(array $types): Collection
    {
        return ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->whereHas('accountType', fn ($query) => $query->whereIn('code', $types))
            ->orderBy('account_code')->get(['id', 'account_code', 'account_name'])->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentReturn(TaxReturn $return): array
    {
        return [
            'id' => $return->id,
            'period_from' => $return->period_from->toDateString(),
            'period_to' => $return->period_to->toDateString(),
            'output_tax' => $return->output_tax,
            'input_tax' => $return->input_tax,
            'net_payable' => $return->net_payable,
            'journal_entry_id' => $return->journal_entry_id,
            'voucher_number' => $return->relationLoaded('journalEntry') ? $return->journalEntry?->getAttribute('voucher_number') : null,
            'reference' => $return->reference,
            'notes' => $return->notes,
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
     * @param  \Closure(): JsonResponse  $action
     */
    private function guardJson(\Closure $action): JsonResponse
    {
        return $action();
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::tax.'.$page, $props)
            : Inertia::render('accounting/tax/'.$page, $props);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
