<?php

namespace Alimarchal\LaravelChartOfAccounts\Jobs;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollPayslipService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Mails one payslip (queued when accounting.payroll.payslip_mail.queue is set).
 */
class SendPayslipEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $companyId, public readonly int $runId, public readonly int $payslipId) {}

    public function handle(PayrollPayslipService $payslips, CurrentCompany $companies): void
    {
        $company = Company::query()->findOrFail($this->companyId);

        $companies->runAs($company, function () use ($payslips): void {
            $payslips->email(PayrollRun::query()->findOrFail($this->runId), Payslip::query()->findOrFail($this->payslipId));
        });
    }
}
