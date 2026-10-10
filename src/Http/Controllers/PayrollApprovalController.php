<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * HR submits a payroll run, finance approves it or sends it back (accounting.payroll.require_approval / the payroll_approval feature).
 */
class PayrollApprovalController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollService $payroll) {}

    public function submit(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->payroll->submit($run), 'The run was submitted for approval.'));
    }

    public function withdraw(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->payroll->withdraw($run), 'The run is a draft again.'));
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->payroll->approve($run), 'The run was approved: it can be posted.'));
    }

    public function reject(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->guard($request, fn () => $this->answer($request, $this->payroll->reject($run, $data['reason']), 'The run was sent back to HR.'));
    }

    private function answer(Request $request, PayrollRun $run, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'data' => ['id' => $run->id, 'status' => $run->status, 'submitted_at' => $run->submitted_at?->toDateTimeString(), 'approved_at' => $run->approved_at?->toDateTimeString(), 'rejection_reason' => $run->rejection_reason]])
            : back()->with('success', $message);
    }
}
