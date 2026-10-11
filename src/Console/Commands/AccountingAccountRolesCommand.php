<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Console\Command;

/**
 * Show which account plays which role for a company, give a role other accounts, or fill the roles of a chart.
 */
class AccountingAccountRolesCommand extends Command
{
    protected $signature = 'accounting:account-roles
        {action=list : list, set or seed}
        {role? : set: the role (see the list)}
        {codes?* : set: the account codes that play it; seed: the chart (erpnext, general or school)}
        {--company= : Company code (default: the default company)}
        {--overwrite : seed: also replace roles that already have accounts}';

    protected $description = 'List the account roles, give a role other accounts, or fill the roles of a chart (erpnext, general, school)';

    public function handle(CurrentCompany $companies): int
    {
        $company = $this->option('company') ? Company::query()->where('code', strtoupper((string) $this->option('company')))->first() : null;

        if ($this->option('company') && $company === null) {
            $this->error("No company with code {$this->option('company')}.");

            return self::FAILURE;
        }

        $run = fn (): int => match ((string) $this->argument('action')) {
            'list' => $this->list(),
            'set' => $this->set(),
            'seed' => $this->seed(),
            default => $this->unknown(),
        };

        return $company ? $companies->runAs($company, $run) : $run();
    }

    private function list(): int
    {
        $this->info('Chart: '.(AccountRoles::presets()[AccountRoles::preset()] ?? AccountRoles::preset()));
        $this->table(['Role', 'What it is', 'Accounts', 'Fixed by'], array_map(fn (array $row): array => [
            $row['role'], $row['label'], collect($row['accounts'])->map(fn (array $account) => $account['account_code'].' '.$account['account_name'])->implode(', ') ?: '—', $row['overridden'] ? $row['config'] : '',
        ], AccountRoles::overview()));

        return self::SUCCESS;
    }

    private function set(): int
    {
        $role = (string) $this->argument('role');
        $entry = AccountRoles::registry()[$role] ?? null;
        $codes = array_map('strval', (array) $this->argument('codes'));

        if ($entry === null) {
            $this->error("'{$role}' is not a role: run `accounting:account-roles` for the list.");

            return self::INVALID;
        }

        if (! $entry['multiple'] && count($codes) > 1) {
            $this->error('This role takes one account.');

            return self::INVALID;
        }

        $accounts = ChartOfAccount::query()->whereIn('account_code', $codes)->where('is_group', false)->where('is_active', true)->pluck('id', 'account_code');
        $missing = array_diff($codes, $accounts->keys()->map(fn ($code) => (string) $code)->all());

        if ($missing !== []) {
            $this->error('No active posting account with code: '.implode(', ', $missing));

            return self::FAILURE;
        }

        AccountRoles::set($role, collect($codes)->map(fn (string $code) => $accounts[$code])->all());
        $this->info("{$role}: ".($codes === [] ? 'no account' : implode(', ', $codes)));

        return self::SUCCESS;
    }

    private function seed(): int
    {
        $chart = (string) ($this->argument('codes')[0] ?? AccountRoles::preset());

        if (! array_key_exists($chart, AccountRoles::presets())) {
            $this->error("Unknown chart '{$chart}': use ".implode(', ', array_keys(AccountRoles::presets())).'.');

            return self::INVALID;
        }

        AccountRoles::seedDefaults($chart, (bool) $this->option('overwrite'));
        $this->info("Roles filled from the {$chart} chart".($this->option('overwrite') ? ' (replacing what was set)' : ' (what was set is kept)').'.');

        return $this->list();
    }

    private function unknown(): int
    {
        $this->error('Unknown action: use list, set or seed.');

        return self::INVALID;
    }
}
