<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Concerns;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyScope;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commands run for one company: the one owning the period passed in, the --company option
 * (code or id), or the default company.
 *
 * @mixin Command
 */
trait RunsForCompany
{
    /**
     * The company chosen by the command applies to this command only: restore the caller's
     * context afterwards (Artisan::call() from a request, a job or a test).
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $companies = app(CurrentCompany::class);
        $previous = $companies->explicit();

        try {
            return parent::execute($input, $output);
        } finally {
            $companies->restore($previous);
        }
    }

    protected function periodForCommand(int|string $periodId): AccountingPeriod
    {
        $period = AccountingPeriod::query()->withoutGlobalScope(CompanyScope::class)->findOrFail($periodId);
        app(CurrentCompany::class)->set(Company::query()->findOrFail($period->company_id));

        return $period;
    }

    protected function useCompanyOption(): Company
    {
        $option = $this->hasOption('company') ? $this->option('company') : null;
        $companies = app(CurrentCompany::class);
        $company = $option ? $companies->find((string) $option) : $companies->default();

        if ($company === null) {
            throw new \InvalidArgumentException("Unknown company \"{$option}\".");
        }

        $companies->set($company);

        return $company;
    }
}
