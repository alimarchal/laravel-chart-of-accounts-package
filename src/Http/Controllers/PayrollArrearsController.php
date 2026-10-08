<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollArrear;
use Alimarchal\LaravelChartOfAccounts\Models\SalaryGrade;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollArrearsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;

/**
 * Arrears (back pay of a raise): work them out, review, approve, and read the register.
 */
class PayrollArrearsController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollArrearsService $arrears) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', 'in:draft,approved,included,cancelled'], 'payment_month' => ['nullable', 'date']]);
        $report = $this->arrears->report($filters['status'] ?? null, $filters['payment_month'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['data' => $report['rows'], 'totals' => $report['totals']]);
        }

        return $this->render('arrears', [
            'rows' => $report['rows'], 'totals' => $report['totals'], 'filters' => $filters,
            'employees' => Employee::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->values(),
            'grades' => SalaryGrade::query()->orderBy('code')->get(['id', 'code', 'name']),
            'preview' => session('arrears_preview'),
            'defaults' => ['from_month' => now()->startOfYear()->toDateString(), 'payment_month' => now()->startOfMonth()->toDateString()],
        ]);
    }

    public function show(PayrollArrear $arrear): JsonResponse
    {
        return response()->json(['data' => $this->arrears->present($arrear)]);
    }

    public function preview(Request $request): RedirectResponse|JsonResponse
    {
        $rows = $this->arrears->calculate($this->arrears->validate($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $rows]) : back()->withInput()->with('arrears_preview', $rows)->with('success', $rows === [] ? 'No arrears are due for those months.' : null);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $created = $this->arrears->create($this->arrears->validate($request->all()));
        $message = count($created).' arrears created for approval.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'data' => array_map(fn (PayrollArrear $arrear): array => $this->arrears->present($arrear), $created)], 201)
            : to_route($this->routeName('payroll.arrears.index'))->with('success', $created === [] ? 'No arrears are due for those months.' : $message);
    }

    public function approve(Request $request, PayrollArrear $arrear): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $arrear) {
            $arrear = $this->arrears->approve($arrear);

            return $request->expectsJson() ? response()->json(['message' => 'Arrears approved.', 'data' => $this->arrears->present($arrear)]) : back()->with('success', 'Arrears approved.');
        });
    }

    public function approveAll(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['payment_month' => ['nullable', 'date']]);

        return $this->guard($request, function () use ($request, $data) {
            $count = $this->arrears->approveAll($data['payment_month'] ?? null);

            return $request->expectsJson() ? response()->json(['message' => $count.' arrears approved.', 'data' => ['approved' => $count]]) : back()->with('success', $count.' arrears approved.');
        });
    }

    public function cancel(Request $request, PayrollArrear $arrear): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $arrear) {
            $arrear = $this->arrears->cancel($arrear);

            return $request->expectsJson() ? response()->json(['message' => 'Arrears cancelled.', 'data' => $this->arrears->present($arrear)]) : back()->with('success', 'Arrears cancelled.');
        });
    }
}
