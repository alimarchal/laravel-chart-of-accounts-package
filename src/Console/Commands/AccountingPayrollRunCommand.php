<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class AccountingPayrollRunCommand extends Command
{
    protected $signature = 'accounting:payroll-run
        {--month= : The month to run, e.g. 2026-10 (default: per accounting.payroll.auto_run.month)}
        {--company= : Company code (default: every company)}';

    protected $description = 'Create the draft payroll run of a month for every company that has none (scheduled monthly when auto_run is on).';

    public function handle(PayrollService $payroll, CurrentCompany $companies): int
    {
        $month = $this->option('month')
            ? Carbon::parse($this->option('month').'-01')->startOfMonth()
            : (config('accounting.payroll.auto_run.month', 'previous') === 'current' ? now()->startOfMonth() : now()->subMonthNoOverflow()->startOfMonth());
        $query = Company::query()->orderBy('id');

        if ($this->option('company')) {
            $query->where('code', strtoupper((string) $this->option('company')));
        }

        $rows = [];
        $failed = 0;

        foreach ($query->get() as $company) {
            $companies->runAs($company, function () use ($payroll, $month, $company, &$rows, &$failed): void {
                if (PayrollRun::query()->whereDate('period_month', $month->toDateString())->where('status', '<>', 'void')->exists()) {
                    $rows[] = [$company->code, $month->format('Y-m'), 'skipped', 'There is already a run for the month.'];

                    return;
                }

                try {
                    $run = $payroll->createRun($month->toDateString(), 'Created by the scheduler');
                    $rows[] = [$company->code, $month->format('Y-m'), 'draft', $run->employees_count ?? ''];
                } catch (Throwable $exception) {
                    $failed++;
                    $rows[] = [$company->code, $month->format('Y-m'), 'failed', $exception->getMessage()];
                }
            });
        }

        $this->table(['Company', 'Month', 'Result', 'Note'], $rows);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
