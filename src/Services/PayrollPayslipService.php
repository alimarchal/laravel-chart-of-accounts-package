<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Jobs\SendPayslipEmail;
use Alimarchal\LaravelChartOfAccounts\Mail\PayslipMail;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\PayslipLine;
use Alimarchal\LaravelChartOfAccounts\Support\AmountInWords;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * The payslip as a document (a printable page, a PDF) and by e-mail to the employee.
 */
class PayrollPayslipService
{
    public function __construct(private readonly PdfRenderer $renderer) {}

    /**
     * @return array<string, mixed>
     */
    public function data(PayrollRun $run, Payslip $slip): array
    {
        $employee = Employee::query()->findOrFail($slip->employee_id);
        $lines = PayslipLine::query()->where('payslip_id', $slip->id)->orderBy('id')->get(['kind', 'description', 'amount']);
        $month = Carbon::parse($run->period_month);

        return [
            'company' => $this->renderer->company(), 'run' => $run, 'slip' => $slip, 'employee' => $employee, 'month' => $month->format('F Y'),
            'earnings' => $lines->whereIn('kind', ['basic', 'earning', 'arrears'])->values(), 'deductions' => $lines->whereIn('kind', ['deduction', 'tax'])->values(), 'employerLines' => $lines->where('kind', 'employer')->values(),
            'title' => 'Payslip', 'subtitle' => $month->format('F Y'), 'amountInWords' => AmountInWords::convert($slip->net, null), 'printedAt' => now()->format('d M Y H:i'),
        ];
    }

    /**
     * The payslip as a file: a PDF when dompdf is installed, otherwise the printable HTML.
     *
     * @return array{content: string, mime: string, filename: string}
     */
    public function render(PayrollRun $run, Payslip $slip): array
    {
        $data = $this->data($run, $slip);
        $name = 'payslip-'.$data['employee']->code.'-'.Carbon::parse($run->period_month)->format('Y-m');

        if ($this->renderer->available()) {
            return ['content' => $this->renderer->render('accounting::pdf.payslip', [...$data, 'forScreen' => false]), 'mime' => 'application/pdf', 'filename' => $name.'.pdf'];
        }

        return ['content' => view('accounting::pdf.payslip', [...$data, 'forScreen' => false])->render(), 'mime' => 'text/html', 'filename' => $name.'.html'];
    }

    public function assertMailable(PayrollRun $run): void
    {
        if (! in_array($run->status, ['posted', 'paid'], true)) {
            throw new AccountingException('Post the payroll first: payslips are mailed from posted salaries.');
        }
    }

    /**
     * Mail one payslip to the employee's address.
     */
    public function email(PayrollRun $run, Payslip $slip): void
    {
        $this->assertMailable($run);
        $employee = Employee::query()->findOrFail($slip->employee_id);

        if (trim((string) $employee->email) === '') {
            throw new AccountingException("{$employee->name} has no e-mail address.");
        }

        $file = $this->render($run, $slip);
        $company = app(CurrentCompany::class)->get();
        Mail::to($employee->email)->send(new PayslipMail($employee->name, Carbon::parse($run->period_month)->format('F Y'), $company->name, $file['content'], $file['mime'], $file['filename']));
        AccountingAuditLog::record($run, 'PAYSLIP_EMAILED', null, null, ['employee' => $employee->code, 'to' => $employee->email]);
    }

    /**
     * Mail every payslip of a run (queued when a queue is configured). Employees without an address are listed, not forgotten.
     *
     * @return array{sent: int, skipped: list<array{code: string, name: string}>}
     */
    public function emailRun(PayrollRun $run): array
    {
        $this->assertMailable($run);
        $company = app(CurrentCompany::class)->get();
        $queue = config('accounting.payroll.payslip_mail.queue');
        $employees = Employee::query()->whereIn('id', Payslip::query()->where('payroll_run_id', $run->id)->select('employee_id'))->get()->keyBy('id');
        $sent = 0;
        $skipped = [];

        foreach (Payslip::query()->where('payroll_run_id', $run->id)->orderBy('id')->get() as $slip) {
            $employee = $employees[$slip->employee_id] ?? null;

            if ($employee === null) {
                continue;
            }

            if (trim((string) $employee->email) === '') {
                $skipped[] = ['code' => $employee->code, 'name' => $employee->name];

                continue;
            }

            $job = new SendPayslipEmail($company->id, $run->id, $slip->id);
            $queue ? dispatch($job->onQueue((string) $queue)) : dispatch_sync($job);
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
