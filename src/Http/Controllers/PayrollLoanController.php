<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\Loan;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollLoanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;

/**
 * Loans and salary advances: create, pay out, skip an instalment, settle, cancel.
 */
class PayrollLoanController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollLoanService $loans) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', 'in:draft,active,closed,cancelled']])['status'] ?? null;
        $employees = Employee::query()->get(['id', 'code', 'name'])->keyBy('id');
        $rows = Loan::query()->when($status, fn ($query, $value) => $query->where('status', $value))->orderByDesc('id')->limit(300)->get()
            ->map(fn (Loan $loan): array => $this->loans->present($loan, $employees[$loan->employee_id] ?? null))->values();

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows]);
        }

        return $this->render('loans', [
            'loans' => $rows, 'status' => $status, 'employees' => $employees->values()->where('id', '>', 0)->values(), 'today' => now()->toDateString(),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    public function show(Loan $loan): JsonResponse
    {
        return response()->json(['data' => $this->loans->present($loan)]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $loan = $this->loans->create($this->loans->validate($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $this->loans->present($loan)], 201) : back()->with('success', 'Loan recorded: pay it out to start the recovery from salary.');
    }

    public function disburse(Request $request, Loan $loan): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'date' => ['nullable', 'date']]);

        return $this->guard($request, fn () => $this->answer($request, $this->loans->disburse($loan, (int) $data['account_id'], $data['date'] ?? null), 'Loan paid out.'));
    }

    public function settle(Request $request, Loan $loan): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'date' => ['nullable', 'date']]);

        return $this->guard($request, fn () => $this->answer($request, $this->loans->settle($loan, (int) $data['account_id'], $data['date'] ?? null), 'Loan settled.'));
    }

    public function skip(Request $request, Loan $loan): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->loans->skip($loan), 'Instalment moved to the end.'));
    }

    public function cancel(Request $request, Loan $loan): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->loans->cancel($loan), 'Loan cancelled.'));
    }

    private function answer(Request $request, Loan $loan, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->loans->present($loan)]) : back()->with('success', $message);
    }
}
