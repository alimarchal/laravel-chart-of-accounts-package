<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Support\ChartTemplates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class AccountingCreateCompanyCommand extends Command
{
    protected $signature = 'accounting:create-company
        {code : Short unique code, e.g. SUB}
        {name : Company name}
        {--fiscal-start=1 : Month the fiscal year starts in (1-12)}
        {--template= : Industry chart template (see accounting:chart-templates), instead of the configured preset}
        {--empty : Do not seed the chart of accounts, period, cost centers and tax codes}
        {--user=* : Email of a user to give access to (repeatable)}';

    protected $description = 'Create a company with its own chart of accounts, fiscal year, cost centers and tax codes.';

    public function handle(CompanyService $companies): int
    {
        $data = $companies->normalize([
            'code' => $this->argument('code'),
            'name' => $this->argument('name'),
            'fiscal_year_start_month' => (int) $this->option('fiscal-start'),
        ]);

        $validator = Validator::make($data, $companies->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $template = $this->option('template') ?: null;

        if ($template !== null && ChartTemplates::find((string) $template) === null) {
            $this->error("Unknown chart template \"{$template}\": run accounting:chart-templates to list them.");

            return self::FAILURE;
        }

        $company = $companies->create($validator->validated(), ! $this->option('empty'), null, $template);
        $this->info("Company {$company->name} ({$company->code}) created.");

        $userModel = config('auth.providers.users.model');

        foreach ((array) $this->option('user') as $email) {
            $user = $userModel::query()->where('email', $email)->first();

            if ($user === null) {
                $this->warn("No user with email {$email}.");

                continue;
            }

            $companies->grantAccess($company, $user);
            $this->line("   Access granted to {$email}.");
        }

        if (! config('accounting.multi_company.enabled')) {
            $this->warn('Multi-company is off: set ACCOUNTING_MULTI_COMPANY=true to work in more than the default company.');
        }

        return self::SUCCESS;
    }
}
