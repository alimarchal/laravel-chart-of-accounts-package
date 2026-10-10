<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollPayslipService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * A payslip as a printable page, a PDF, and by e-mail (one, or every payslip of a run).
 */
class PayrollPayslipController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollPayslipService $payslips) {}

    public function print(PayrollRun $run, Payslip $payslip): View
    {
        abort_unless($payslip->payroll_run_id === $run->id, 404);

        return view('accounting::pdf.payslip', [...$this->payslips->data($run, $payslip), 'forScreen' => true]);
    }

    public function pdf(PayrollRun $run, Payslip $payslip): Response
    {
        abort_unless($payslip->payroll_run_id === $run->id, 404);
        $file = $this->payslips->render($run, $payslip);

        return response($file['content'], 200, ['Content-Type' => $file['mime'], 'Content-Disposition' => 'inline; filename="'.$file['filename'].'"']);
    }

    public function email(Request $request, PayrollRun $run, Payslip $payslip): RedirectResponse|JsonResponse
    {
        abort_unless($payslip->payroll_run_id === $run->id, 404);

        return $this->guard($request, function () use ($request, $run, $payslip) {
            $this->payslips->email($run, $payslip);

            return $request->expectsJson() ? response()->json(['message' => 'Payslip sent.']) : back()->with('success', 'Payslip sent.');
        });
    }

    public function emailAll(Request $request, PayrollRun $run): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $run) {
            $result = $this->payslips->emailRun($run);
            $message = $result['sent'].' payslips sent'.($result['skipped'] === [] ? '.' : '; '.count($result['skipped']).' employees have no e-mail address: '.collect($result['skipped'])->pluck('code')->implode(', ').'.');

            return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $result]) : back()->with('success', $message);
        });
    }
}
