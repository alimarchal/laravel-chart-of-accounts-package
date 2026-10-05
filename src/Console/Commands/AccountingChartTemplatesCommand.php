<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Services\ChartTemplateService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Console\Command;

class AccountingChartTemplatesCommand extends Command
{
    protected $signature = 'accounting:chart-templates
        {template? : Template to apply (omit to list them)}
        {--company= : Company code (default: the default company)}
        {--dry-run : Show what would be added without changing anything}';

    protected $description = 'List the industry chart templates, or add one to a company (existing accounts are never changed).';

    public function handle(ChartTemplateService $templates, CurrentCompany $companies): int
    {
        $key = $this->argument('template');

        if ($key === null) {
            $this->table(['Key', 'Name', 'Accounts', 'Industry-specific'], array_map(fn (array $t) => [$t['key'], $t['name'], $t['accounts'], $t['extras']], $templates->available()));

            return self::SUCCESS;
        }

        $company = $this->option('company') ? Company::query()->where('code', strtoupper((string) $this->option('company')))->first() : null;

        if ($this->option('company') && $company === null) {
            $this->error("No company with code {$this->option('company')}.");

            return self::FAILURE;
        }

        $run = function () use ($templates, $key): int {
            try {
                if ($this->option('dry-run')) {
                    $preview = $templates->preview((string) $key);
                    $this->info("{$preview['template']['name']}: {$preview['summary']['new']} new, {$preview['summary']['exists']} already there, {$preview['summary']['different']} with another name, {$preview['summary']['blocked']} blocked.");
                    $this->table(['Code', 'Name', 'Status'], array_map(fn (array $row) => [$row['account_code'], $row['account_name'], $row['status']], array_filter($preview['rows'], fn (array $row) => $row['status'] !== 'exists')));

                    return self::SUCCESS;
                }

                $result = $templates->apply((string) $key);
                $this->info(count($result['created']).' accounts added, '.$result['skipped'].' left as they are.');

                return self::SUCCESS;
            } catch (AccountingException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        };

        return $company ? $companies->runAs($company, $run) : $run();
    }
}
