<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\EmployeeComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employees, pay components, payroll runs and payslips for the React and Blade screens and the API.
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    // -- runs --------------------------------------------------------------------------------------------------

    public function index(Request $request): Response|View|JsonResponse
    {
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? now()->format('Y'));
        $runs = PayrollRun::query()->orderByDesc('period_month')->orderByDesc('id')->limit(60)->get()->map(fn (PayrollRun $run): array => $this->presentRun($run))->values();

        return $request->expectsJson()
            ? response()->json(['data' => $runs])
            : $this->render('index', ['runs' => $runs, 'summary' => $this->payroll->summary($year), 'year' => $year, 'defaultMonth' => now()->startOfMonth()->toDateString()]);
    }

    public function runStore(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['period_month' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:500']]);

        return $this->guard($request, function () use ($request, $data) {
            $run = $this->payroll->createRun($data['period_month'], $data['notes'] ?? null);

            return $request->expectsJson() ? response()->json(['data' => $this->presentRun($run)], 201) : to_route($this->routeName('payroll.runs.show'), $run)->with('success', 'Payroll run created.');
        });
    }

    public function runShow(Request $request, PayrollRun $run): Response|View|JsonResponse
    {
        $slips = $this->slips($run);
        $props = [
            'run' => $this->presentRun($run), 'payslips' => $slips,
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->with('accountType:id,code')->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type_id'])
                ->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name, 'type' => $account->accountType->code])->values(),
            'today' => now()->toDateString(),
        ];

        return $request->expectsJson() ? response()->json(['data' => ['run' => $props['run'], 'payslips' => $slips]]) : $this->render('run-show', $props);
    }

    public function runRecalculate(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->payroll->recalculate($run), 'Payslips recalculated.'));
    }

    public function runPost(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['payable_account_id' => ['nullable', 'integer'], 'date' => ['nullable', 'date']]);

        return $this->guard($request, fn () => $this->answer($request, $this->payroll->post($run, isset($data['payable_account_id']) ? (int) $data['payable_account_id'] : null, $data['date'] ?? null), 'Payroll posted.'));
    }

    public function runPay(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'date' => ['nullable', 'date']]);

        return $this->guard($request, fn () => $this->answer($request, $this->payroll->pay($run, (int) $data['account_id'], $data['date'] ?? null), 'Salaries paid.'));
    }

    public function runVoid(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->payroll->void($run), 'Payroll voided.'));
    }

    public function runDestroy(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $run) {
            $this->payroll->deleteRun($run);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('payroll.index'))->with('success', 'Payroll run deleted.');
        });
    }

    public function payslipShow(Request $request, PayrollRun $run, Payslip $payslip): Response|View|JsonResponse
    {
        abort_unless($payslip->payroll_run_id === $run->id, 404);
        $slip = $this->slips($run, $payslip->id)[0] ?? abort(404);
        $employee = Employee::query()->findOrFail($payslip->employee_id);
        $props = ['run' => $this->presentRun($run), 'payslip' => $slip, 'employee' => ['code' => $employee->code, 'name' => $employee->name, 'designation' => $employee->designation, 'national_id' => $employee->national_id, 'bank_name' => $employee->bank_name, 'bank_account' => $employee->bank_account]];

        return $request->expectsJson() ? response()->json(['data' => $props]) : $this->render('payslip', $props);
    }

    // -- employees ---------------------------------------------------------------------------------------------

    public function employees(Request $request): Response|View|JsonResponse
    {
        $employees = Employee::query()->orderBy('code')->get()->map(fn (Employee $employee): array => $this->presentEmployee($employee))->values();

        return $request->expectsJson() ? response()->json(['data' => $employees]) : $this->render('employees', ['employees' => $employees]);
    }

    public function employeeCreate(): Response|View
    {
        return $this->render('employee-form', $this->employeeFormProps(null));
    }

    public function employeeEdit(Employee $employee): Response|View
    {
        return $this->render('employee-form', $this->employeeFormProps($employee));
    }

    public function employeeShow(Employee $employee): JsonResponse
    {
        return response()->json(['data' => $this->presentEmployee($employee, true)]);
    }

    public function employeeStore(Request $request): RedirectResponse|JsonResponse
    {
        $employee = $this->payroll->saveEmployee($this->payroll->validateEmployee($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $this->presentEmployee($employee, true)], 201) : to_route($this->routeName('payroll.employees.index'))->with('success', 'Employee saved.');
    }

    public function employeeUpdate(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        $employee = $this->payroll->saveEmployee($this->payroll->validateEmployee($request->all(), $employee), $employee);

        return $request->expectsJson() ? response()->json(['data' => $this->presentEmployee($employee, true)]) : to_route($this->routeName('payroll.employees.index'))->with('success', 'Employee saved.');
    }

    public function employeeDestroy(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $employee) {
            $this->payroll->deleteEmployee($employee);

            return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Employee deleted.');
        });
    }

    // -- components --------------------------------------------------------------------------------------------

    public function components(Request $request): Response|View|JsonResponse
    {
        $components = PayComponent::query()->orderBy('kind')->orderBy('code')->get()->map(fn (PayComponent $component): array => $this->presentComponent($component))->values();

        return $request->expectsJson() ? response()->json(['data' => $components]) : $this->render('components', ['components' => $components, 'accounts' => $this->accountOptions()]);
    }

    public function componentStore(Request $request): RedirectResponse|JsonResponse
    {
        $component = PayComponent::query()->create($this->payroll->validateComponent($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $this->presentComponent($component)], 201) : back()->with('success', 'Component added.');
    }

    public function componentUpdate(Request $request, PayComponent $component): RedirectResponse|JsonResponse
    {
        $component->update($this->payroll->validateComponent($request->all(), $component));

        return $request->expectsJson() ? response()->json(['data' => $this->presentComponent($component->refresh())]) : back()->with('success', 'Component updated.');
    }

    public function componentDestroy(Request $request, PayComponent $component): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $component) {
            $this->payroll->deleteComponent($component);

            return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Component deleted.');
        });
    }

    // -- helpers -----------------------------------------------------------------------------------------------

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

    private function answer(Request $request, PayrollRun $run, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->presentRun($run)]) : back()->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::payroll.'.$page, $props)
            : Inertia::render('accounting/payroll/'.$page, $props);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountOptions(): array
    {
        return ChartOfAccount::query()->with('accountType:id,code')->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type_id'])
            ->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name, 'type' => $account->accountType->code])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeFormProps(?Employee $employee): array
    {
        return [
            'employee' => $employee ? $this->presentEmployee($employee, true) : null,
            'components' => PayComponent::query()->where('is_active', true)->orderBy('kind')->orderBy('code')->get()->map(fn (PayComponent $component): array => $this->presentComponent($component))->values(),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'today' => now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRun(PayrollRun $run): array
    {
        return [
            'id' => $run->id, 'period_month' => Carbon::parse($run->period_month)->format('Y-m'), 'status' => $run->status, 'gross' => $run->gross, 'deductions' => $run->deductions, 'tax' => $run->tax, 'net' => $run->net,
            'journal_entry_id' => $run->journal_entry_id, 'payment_entry_id' => $run->payment_entry_id, 'posted_on' => $run->posted_on?->toDateString(), 'paid_on' => $run->paid_on?->toDateString(), 'notes' => $run->notes,
            'employees' => Payslip::query()->where('payroll_run_id', $run->id)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function slips(PayrollRun $run, ?int $only = null): array
    {
        $employees = Employee::query()->pluck('name', 'id');
        $codes = Employee::query()->pluck('code', 'id');
        $slips = Payslip::query()->where('payroll_run_id', $run->id)->when($only, fn ($query, $id) => $query->where('id', $id))->orderBy('id')->get();
        $lines = PayslipLine::query()->whereIn('payslip_id', $slips->pluck('id'))->orderBy('id')->get()->groupBy('payslip_id');

        return $slips->map(fn (Payslip $slip): array => [
            'id' => $slip->id, 'employee_id' => $slip->employee_id, 'employee_code' => $codes[$slip->employee_id] ?? '', 'employee_name' => $employees[$slip->employee_id] ?? '',
            'basic' => $slip->basic, 'gross' => $slip->gross, 'deductions' => $slip->deductions, 'tax' => $slip->tax, 'net' => $slip->net, 'days_paid' => $slip->days_paid, 'days_in_month' => $slip->days_in_month,
            'lines' => collect($lines[$slip->id] ?? [])->map(fn (PayslipLine $line): array => ['kind' => $line->kind, 'description' => $line->description, 'amount' => $line->amount])->values()->all(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEmployee(Employee $employee, bool $withComponents = false): array
    {
        $data = [
            'id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'national_id' => $employee->national_id, 'designation' => $employee->designation, 'cost_center_id' => $employee->cost_center_id,
            'join_date' => $employee->join_date->toDateString(), 'leave_date' => $employee->leave_date?->toDateString(), 'base_salary' => $employee->base_salary, 'withhold_tax' => $employee->withhold_tax,
            'bank_name' => $employee->bank_name, 'bank_account' => $employee->bank_account, 'is_active' => $employee->is_active,
        ];

        if ($withComponents) {
            $data['components'] = EmployeeComponent::query()->where('employee_id', $employee->id)->get(['pay_component_id', 'value'])->map(fn (EmployeeComponent $link): array => ['pay_component_id' => $link->pay_component_id, 'value' => $link->value])->values()->all();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentComponent(PayComponent $component): array
    {
        return ['id' => $component->id, 'code' => $component->code, 'name' => $component->name, 'kind' => $component->kind, 'method' => $component->method, 'value' => $component->value, 'taxable' => $component->taxable, 'account_id' => $component->account_id, 'is_active' => $component->is_active];
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
