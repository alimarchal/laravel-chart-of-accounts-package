<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Budget;
use Alimarchal\LaravelChartOfAccounts\Models\BudgetLine;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\BudgetService;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Budgets and budget-versus-actual for the React and Blade screens and the API (JSON when the request expects it).
 */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $budgets = Budget::query()->withCount('lines')->orderByDesc('start_date')->orderBy('name')->get()->map(fn (Budget $budget): array => $this->present($budget))->values();

        return $request->expectsJson() ? response()->json(['data' => $budgets]) : $this->render('index', ['budgets' => $budgets]);
    }

    public function create(): Response|View
    {
        return $this->render('form', $this->formProps(null));
    }

    public function edit(Budget $budget): Response|View
    {
        abort_unless($budget->status === 'draft', 403, 'Only a draft budget can be edited.');

        return $this->render('form', $this->formProps($budget));
    }

    public function show(Request $request, Budget $budget): Response|View|JsonResponse
    {
        $filters = $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'cost_center_id' => ['nullable', 'integer']]);
        $report = $this->budgets->report($budget, $filters);

        if ($request->expectsJson()) {
            return response()->json(['data' => ['budget' => $this->present($budget), ...$report]]);
        }

        return $this->render('show', [
            'budget' => $this->present($budget),
            'report' => $report,
            'filters' => ['date_from' => $filters['date_from'] ?? null, 'date_to' => $filters['date_to'] ?? null, 'cost_center_id' => $filters['cost_center_id'] ?? null],
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $budget = $this->budgets->create($this->budgets->validate($request->all()));

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($budget, true)], 201)
                : to_route($this->routeName('budgets.show'), $budget)->with('success', 'Budget created.');
        });
    }

    public function update(Request $request, Budget $budget): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $budget) {
            $budget = $this->budgets->update($budget, $this->budgets->validate($request->all(), $budget));

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($budget, true)])
                : to_route($this->routeName('budgets.show'), $budget)->with('success', 'Budget updated.');
        });
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $budget) {
            $this->budgets->delete($budget);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('budgets.index'))->with('success', 'Budget deleted.');
        });
    }

    public function approve(Request $request, Budget $budget): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->budgets->approve($budget), 'Budget approved.'));
    }

    public function reopen(Request $request, Budget $budget): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->budgets->reopen($budget), 'Budget reopened as a draft.'));
    }

    public function close(Request $request, Budget $budget): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->budgets->close($budget), 'Budget closed.'));
    }

    public function copy(Request $request, Budget $budget): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'uplift_percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
        ]);

        return $this->guard($request, function () use ($request, $budget, $data) {
            $copy = $this->budgets->copy($budget, $data['name'], $data['start_date'] ?? null, (float) ($data['uplift_percent'] ?? 0));

            return $request->expectsJson()
                ? response()->json(['data' => $this->present($copy, true)], 201)
                : to_route($this->routeName('budgets.show'), $copy)->with('success', 'Budget copied as a draft.');
        });
    }

    public function export(Request $request, Budget $budget, string $format, AccountingReportExporter $exporter): HttpResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $filters = $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'cost_center_id' => ['nullable', 'integer']]);
        $report = $this->budgets->report($budget, $filters);
        $rows = collect($report['rows'])->map(fn (array $row): array => [
            'account_code' => $row['account_code'],
            'account_name' => $row['account_name'],
            'type' => $row['type'],
            'budget' => $row['budget'],
            'actual' => $row['actual'],
            'variance' => $row['variance'],
            'used_percent' => $row['used_percent'] === null ? '' : (string) $row['used_percent'],
            'status' => $row['status'],
        ]);

        return $exporter->download($rows, 'budget-vs-actual-'.str($budget->name)->slug(), $format, ['title' => "Budget vs actual: {$budget->name}", 'filters' => ['From' => $report['date_from'], 'To' => $report['date_to']]]);
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

    private function answer(Request $request, Budget $budget, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->present($budget)]) : back()->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::budgets.'.$page, $props)
            : Inertia::render('accounting/budgets/'.$page, $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Budget $budget): array
    {
        return [
            'budget' => $budget ? $this->present($budget, true) : null,
            'accounts' => ChartOfAccount::query()->with('accountType:id,code')->where('is_group', false)->where('is_active', true)
                ->whereHas('accountType', fn ($query) => $query->where('report_group', 'IncomeStatement'))->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type_id'])
                ->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name, 'type' => $account->accountType->code])->values(),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'today' => now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Budget $budget, bool $detailed = false): array
    {
        $data = [
            'id' => $budget->id,
            'name' => $budget->name,
            'status' => $budget->status,
            'start_date' => $budget->start_date->toDateString(),
            'end_date' => $budget->end_date->toDateString(),
            'notes' => $budget->notes,
            'lines_count' => isset($budget->getAttributes()['lines_count']) ? (int) $budget->getAttributes()['lines_count'] : null,
            'approved_at' => $budget->approved_at?->toISOString(),
        ];

        if ($detailed) {
            $grouped = [];

            foreach (BudgetLine::query()->where('budget_id', $budget->id)->orderBy('chart_of_account_id')->orderBy('month_start')->get() as $line) {
                $key = $line->chart_of_account_id.'|'.$line->cost_center_id;
                $grouped[$key]['chart_of_account_id'] = $line->chart_of_account_id;
                $grouped[$key]['cost_center_id'] = $line->cost_center_id;
                $grouped[$key]['amounts'][$line->month_start->format('Y-m')] = $line->amount;
                $grouped[$key]['annual'] = Money::fromCents(Money::toCents($grouped[$key]['annual'] ?? '0') + Money::toCents($line->amount));
            }

            $data['lines'] = array_values($grouped);
        }

        return $data;
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
