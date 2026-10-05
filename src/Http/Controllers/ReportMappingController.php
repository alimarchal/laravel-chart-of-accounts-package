<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Alimarchal\LaravelChartOfAccounts\Services\ReportMappingService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Report mapping screen (React and Blade) and API: the statement lines and which line each account reports under.
 */
class ReportMappingController extends Controller
{
    public function __construct(private readonly ReportMappingService $mapping) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $data = [
            'lines' => $this->presentLines(),
            'accounts' => $this->presentAccounts(),
            'sections' => ReportLine::SECTIONS,
            'cashFlowCategories' => ReportLine::CASH_FLOW,
            'unmapped' => $this->mapping->unmapped()->count(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => ['lines' => $data['lines'], 'accounts' => $data['accounts'], 'unmapped' => $data['unmapped']]]);
        }

        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::report-mapping.index', [...$data, 'statement' => $request->query('statement', ReportLine::BALANCE_SHEET), 'onlyUnmapped' => $request->boolean('unmapped')])
            : Inertia::render('accounting/report-mapping/index', $data);
    }

    public function updateAccount(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'report_line_id' => ['nullable', 'integer'],
            'cash_flow_category' => ['nullable', 'string'],
        ]);

        return $this->respond($request, function () use ($chartOfAccount, $data): array {
            $this->mapping->setMapping($chartOfAccount, isset($data['report_line_id']) ? (int) $data['report_line_id'] : null, ($data['cash_flow_category'] ?? null) ?: null);

            return ["{$chartOfAccount->account_code} mapped.", $this->presentAccounts([$chartOfAccount->id])[0] ?? null];
        });
    }

    public function recommended(Request $request): RedirectResponse|JsonResponse
    {
        return $this->respond($request, function (): array {
            $applied = $this->mapping->applyRecommended();

            return [$applied === [] ? 'Every recommended account is already mapped.' : count($applied).' accounts mapped to their recommended lines.', $applied];
        });
    }

    public function storeLine(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validateLine($request, true);

        return $this->respond($request, fn () => ['Line added.', $this->mapping->saveLine($data)], 201);
    }

    public function updateLine(Request $request, ReportLine $reportLine): RedirectResponse|JsonResponse
    {
        $data = $this->validateLine($request, false);

        return $this->respond($request, fn () => ['Line updated.', $this->mapping->saveLine($data, $reportLine)]);
    }

    public function destroyLine(Request $request, ReportLine $reportLine): RedirectResponse|JsonResponse
    {
        return $this->respond($request, function () use ($reportLine): array {
            $this->mapping->deleteLine($reportLine);

            return ['Line deleted.', null];
        }, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLine(Request $request, bool $create): array
    {
        $data = $request->validate([
            'statement' => [$create ? 'required' : 'prohibited', 'in:balance_sheet,income_statement'],
            'code' => [$create ? 'required' : 'sometimes', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:255'],
            'section' => [$create ? 'required' : 'sometimes', 'string', 'max:30'],
            'cash_flow_category' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->has('cash_flow_category') && ($data['cash_flow_category'] ?? '') === '') {
            $data['cash_flow_category'] = null;
        }

        return $data;
    }

    /**
     * Run an action and answer with JSON (API) or a redirect with a flash message (screens).
     *
     * @param  \Closure(): array{0: string, 1: mixed}  $action
     */
    private function respond(Request $request, \Closure $action, int $status = 200): RedirectResponse|JsonResponse
    {
        try {
            [$message, $data] = $action();
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return $status === 204 ? response()->json(null, 204) : response()->json(['message' => $message, 'data' => $data], $status);
        }

        return back()->with('success', $message);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentLines(): array
    {
        $counts = DB::table('accounting_chart_of_accounts')->whereIn('company_id', CurrentCompany::ids())->whereNotNull('report_line_id')
            ->groupBy('report_line_id')->selectRaw('report_line_id, COUNT(*) as total')->pluck('total', 'report_line_id');

        return $this->mapping->lines()->map(fn (ReportLine $line) => [
            'id' => $line->id,
            'statement' => $line->statement,
            'code' => $line->code,
            'name' => $line->name,
            'section' => $line->section,
            'cash_flow_category' => $line->cash_flow_category,
            'sort_order' => $line->sort_order,
            'is_system' => $line->is_system,
            'accounts_count' => (int) ($counts[$line->id] ?? 0),
        ])->values()->all();
    }

    /**
     * @param  list<int>|null  $ids
     * @return list<array<string, mixed>>
     */
    private function presentAccounts(?array $ids = null): array
    {
        $resolved = $this->mapping->resolve();

        return DB::table('accounting_chart_of_accounts as coa')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->whereIn('coa.company_id', CurrentCompany::ids())
            ->when($ids, fn ($query, array $values) => $query->whereIn('coa.id', $values))
            ->orderBy('coa.account_code')
            ->get(['coa.id', 'coa.account_code', 'coa.account_name', 'coa.parent_id', 'coa.is_group', 'coa.is_active', 'coa.report_line_id', 'coa.cash_flow_category', 'type.report_group'])
            ->map(fn ($account) => [
                'id' => (int) $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'parent_id' => $account->parent_id === null ? null : (int) $account->parent_id,
                'is_group' => (bool) $account->is_group,
                'is_active' => (bool) $account->is_active,
                'statement' => $account->report_group === 'IncomeStatement' ? ReportLine::INCOME_STATEMENT : ReportLine::BALANCE_SHEET,
                'report_line_id' => $account->report_line_id === null ? null : (int) $account->report_line_id,
                'cash_flow_category' => $account->cash_flow_category,
                'resolved_line_id' => $resolved[(int) $account->id]['line_id'] ?? null,
                'resolved_cash_flow_category' => $resolved[(int) $account->id]['cash_flow_category'] ?? null,
                'inherited' => $resolved[(int) $account->id]['inherited'] ?? false,
            ])->values()->all();
    }
}
