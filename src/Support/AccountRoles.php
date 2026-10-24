<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingSetting;
use Alimarchal\LaravelChartOfAccounts\Models\AccountRole;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Support\Charts\ErpNextChart;
use Illuminate\Support\Facades\Schema;

/**
 * The part each account plays in a company: cash, bank, retained earnings, salary expense, the receivables control account ...
 * The modules ask for a role, never for an account code, so the same code works on any chart of accounts.
 *
 * A role resolves to, in this order: (1) the config value, if the application set one (accounting.payroll.salary_expense_account ...);
 * (2) the accounts saved for the company (the chart seeder fills them in, the Account roles screen and API change them);
 * (3) the code the package's own chart uses, so a database without saved roles keeps working.
 */
final class AccountRoles
{
    public const SETTING_PRESET = 'chart_preset';

    /**
     * role => label, the config key that overrides it, whether it holds several accounts, and the default codes of each seeded chart.
     *
     * @return array<string, array{label: string, config: string|null, multiple: bool, defaults: array<string, list<string>>}>
     */
    public static function registry(): array
    {
        return [
            'cash' => ['label' => 'Cash in hand (cash book)', 'config' => 'accounting.defaults.cash_account_code', 'multiple' => false, 'defaults' => ['general' => ['1101'], 'school' => ['1101'], 'erpnext' => ['1100']]],
            'bank' => ['label' => 'Bank accounts (bank book, close checklist)', 'config' => 'accounting.defaults.bank_account_code', 'multiple' => false, 'defaults' => ['general' => ['1102'], 'school' => ['1102'], 'erpnext' => ['1200']]],
            'retained_earnings' => ['label' => 'Retained earnings (year-end close)', 'config' => 'accounting.defaults.retained_earnings_account_code', 'multiple' => false, 'defaults' => ['general' => ['3101'], 'school' => ['3101'], 'erpnext' => ['3400']]],
            'opening_balance_equity' => ['label' => 'Opening balance equity (carries the difference of the opening balances)', 'config' => null, 'multiple' => false, 'defaults' => ['general' => ['3102'], 'school' => ['3102'], 'erpnext' => ['3300']]],
            'rounding' => ['label' => 'Rounding difference', 'config' => 'accounting.defaults.rounding_account_code', 'multiple' => false, 'defaults' => ['general' => ['5201'], 'school' => ['5201'], 'erpnext' => ['5212']]],
            'fx_gain_loss' => ['label' => 'Exchange gain or loss (revaluation)', 'config' => 'accounting.fx.gain_loss_account', 'multiple' => false, 'defaults' => ['general' => [], 'school' => [], 'erpnext' => ['5219']]],
            'aging.receivables' => ['label' => 'Receivables in the ageing report', 'config' => 'accounting.aging.receivable_account_codes', 'multiple' => true, 'defaults' => ['general' => ['1103', '1104'], 'school' => ['1103', '1104'], 'erpnext' => ['1310']]],
            'aging.payables' => ['label' => 'Payables in the ageing report', 'config' => 'accounting.aging.payable_account_codes', 'multiple' => true, 'defaults' => ['general' => ['2101', '2102', '2103', '2104'], 'school' => ['2101', '2102', '2103', '2104'], 'erpnext' => ['2110', '2120', '2150', '2310', '2320']]],
            'control.receivables' => ['label' => 'Control account: receivables (customers)', 'config' => null, 'multiple' => true, 'defaults' => ['general' => ['1103'], 'school' => ['1103'], 'erpnext' => ['1310']]],
            'control.payables' => ['label' => 'Control account: payables (suppliers)', 'config' => null, 'multiple' => true, 'defaults' => ['general' => ['2101'], 'school' => ['2101'], 'erpnext' => ['2110']]],
            'control.inventory' => ['label' => 'Control accounts: inventory (stock)', 'config' => null, 'multiple' => true, 'defaults' => ['general' => ['1151', '1152', '1153'], 'school' => ['1151', '1152', '1153'], 'erpnext' => ['1410']]],
            'control.payroll' => ['label' => 'Control account: payroll', 'config' => null, 'multiple' => true, 'defaults' => ['general' => ['2103'], 'school' => ['2103'], 'erpnext' => ['2120']]],
            'control.tax' => ['label' => 'Control accounts: tax', 'config' => null, 'multiple' => true, 'defaults' => ['general' => ['1107', '2104'], 'school' => ['1107', '2104'], 'erpnext' => ['1510', '2310', '2320']]],
            'salary_expense' => ['label' => 'Salary expense (payroll)', 'config' => 'accounting.payroll.salary_expense_account', 'multiple' => false, 'defaults' => ['general' => ['5101'], 'school' => ['5101'], 'erpnext' => ['5213']]],
            'income_tax_payable' => ['label' => 'Income tax withheld from salaries (payable)', 'config' => 'accounting.payroll.income_tax_account', 'multiple' => false, 'defaults' => ['general' => ['2104'], 'school' => ['2104'], 'erpnext' => ['2310']]],
            'net_salary_payable' => ['label' => 'Net salary payable', 'config' => 'accounting.payroll.net_payable_account', 'multiple' => false, 'defaults' => ['general' => ['2103'], 'school' => ['2103'], 'erpnext' => ['2120']]],
            'employee_advances' => ['label' => 'Employee loans and advances', 'config' => 'accounting.payroll.employee_loans_account', 'multiple' => false, 'defaults' => ['general' => ['1105'], 'school' => ['1105'], 'erpnext' => ['1610']]],
            'salary_arrears' => ['label' => 'Salary arrears (default: salary expense)', 'config' => 'accounting.payroll.arrears_account', 'multiple' => false, 'defaults' => ['general' => [], 'school' => [], 'erpnext' => []]],
            'bonus_expense' => ['label' => 'Bonuses (default: salary expense)', 'config' => 'accounting.payroll.bonus_account', 'multiple' => false, 'defaults' => ['general' => [], 'school' => [], 'erpnext' => []]],
            'gratuity_expense' => ['label' => 'Gratuity expense (default: salary expense)', 'config' => 'accounting.payroll.gratuity.expense_account', 'multiple' => false, 'defaults' => ['general' => [], 'school' => [], 'erpnext' => []]],
            'gratuity_payable' => ['label' => 'Settlements payable (default: net salary payable)', 'config' => 'accounting.payroll.gratuity.payable_account', 'multiple' => false, 'defaults' => ['general' => [], 'school' => [], 'erpnext' => []]],
        ];
    }

    /**
     * The account types (codes) each role accepts: a role the modules post to must point at an account of the right kind
     * (retained earnings is equity, cash and bank are assets, salary expense is an expense, …).
     *
     * @return list<string>
     */
    public static function allowedTypes(string $role): array
    {
        return match ($role) {
            'cash', 'bank', 'aging.receivables', 'control.receivables', 'control.inventory', 'employee_advances' => ['ASSET'],
            'retained_earnings', 'opening_balance_equity' => ['EQUITY'],
            'rounding', 'fx_gain_loss' => ['INCOME', 'EXPENSE'],
            'aging.payables', 'control.payables', 'control.payroll', 'income_tax_payable', 'net_salary_payable', 'gratuity_payable' => ['LIABILITY'],
            'control.tax' => ['ASSET', 'LIABILITY'],
            'salary_expense', 'salary_arrears', 'bonus_expense', 'gratuity_expense' => ['EXPENSE'],
            default => [],
        };
    }

    /**
     * The problem with giving these accounts to a role, or null when they all suit it.
     *
     * @param  list<int>  $accountIds
     */
    public static function typeProblem(string $role, array $accountIds): ?string
    {
        $allowed = self::allowedTypes($role);

        if ($allowed === [] || $accountIds === []) {
            return null;
        }

        $wrong = ChartOfAccount::query()->with('accountType:id,code')->whereIn('id', $accountIds)->get()
            ->reject(fn (ChartOfAccount $account) => in_array(data_get($account, 'accountType.code'), $allowed, true));

        return $wrong->isEmpty() ? null : 'Choose '.strtolower(implode(' or ', $allowed)).' accounts for this role ('.$wrong->pluck('account_code')->implode(', ').' '.($wrong->count() === 1 ? 'is' : 'are').' not).';
    }

    /**
     * The charts the package can seed: key => name.
     *
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return ['erpnext' => 'ERPNext standard chart (recommended)', 'general' => 'General business chart', 'school' => 'School chart'];
    }

    /**
     * The chart the current company started from (its saved setting, else accounting.chart_preset).
     */
    public static function preset(): string
    {
        $store = self::store();
        $key = CurrentCompany::currentId().'|_preset';

        return $store[$key] ??= self::resolvePreset();
    }

    /**
     * The saved chart of the company; before one is saved, accounting.chart_preset. A company that already has accounts but no saved chart
     * (it was set up by an older version) is the general chart, whatever the new default is.
     */
    private static function resolvePreset(): string
    {
        $saved = AccountingSetting::read(self::SETTING_PRESET);

        if ($saved !== null && $saved !== '' && array_key_exists($saved, self::presets())) {
            return $saved;
        }

        $configured = (string) config('accounting.chart_preset', 'erpnext');

        if (! array_key_exists($configured, self::presets())) {
            return 'general';
        }

        return $configured === 'erpnext' && ChartOfAccount::query()->exists() ? 'general' : $configured;
    }

    /**
     * The first account code of a role, or null when nothing plays it.
     */
    public static function code(string $role): ?string
    {
        return self::codes($role)[0] ?? null;
    }

    /**
     * All the account codes of a role.
     *
     * @return list<string>
     */
    public static function codes(string $role): array
    {
        $entry = self::registry()[$role] ?? null;

        if ($entry === null) {
            return [];
        }

        $configured = $entry['config'] === null ? null : config($entry['config']);

        if ($configured !== null && $configured !== '' && $configured !== []) {
            return array_values(array_map('strval', (array) $configured));
        }

        $saved = self::saved($role);

        if ($saved !== []) {
            return $saved;
        }

        return $entry['defaults'][self::preset()] ?? $entry['defaults']['general'];
    }

    /**
     * The account codes that core guards depend on: cash, bank, retained earnings and rounding (config override or the company's saved roles):
     * those accounts may not be renumbered, deactivated, merged away or deleted.
     *
     * @return list<string>
     */
    public static function protectedCodes(): array
    {
        $codes = [];

        foreach (['cash', 'bank', 'retained_earnings', 'rounding'] as $role) {
            $entry = self::registry()[$role];
            $configured = $entry['config'] === null ? null : config($entry['config']);
            $own = ($configured !== null && $configured !== '' && $configured !== [])
                ? array_map('strval', (array) $configured)
                : self::saved($role);

            array_push($codes, ...$own);
        }

        return array_values(array_unique($codes));
    }

    public static function account(string $role): ?ChartOfAccount
    {
        $code = self::code($role);

        return $code === null ? null : ChartOfAccount::query()->where('account_code', $code)->first();
    }

    /**
     * Save the accounts that play a role in the current company (replacing what was there).
     *
     * @param  list<int>  $accountIds
     */
    public static function set(string $role, array $accountIds): void
    {
        AccountRole::query()->where('role', $role)->delete();

        foreach (array_values(array_unique($accountIds)) as $position => $id) {
            AccountRole::query()->create(['role' => $role, 'chart_of_account_id' => $id, 'position' => $position]);
        }

        self::flush();
    }

    /**
     * Fill the roles a chart defines (by its codes) for the current company. Roles that already have accounts are kept unless $overwrite.
     */
    public static function seedDefaults(string $preset, bool $overwrite = false): void
    {
        if (AccountingSetting::read(self::SETTING_PRESET) === null) {
            AccountingSetting::write(self::SETTING_PRESET, $preset);
        }

        self::flush();

        foreach (self::registry() as $role => $entry) {
            if (! $overwrite && AccountRole::query()->where('role', $role)->exists()) {
                continue;
            }

            $ids = ChartOfAccount::query()->whereIn('account_code', $entry['defaults'][$preset] ?? $entry['defaults']['general'])->orderBy('account_code')->pluck('id', 'account_code');
            $ordered = collect($entry['defaults'][$preset] ?? $entry['defaults']['general'])->map(fn (string $code) => $ids[$code] ?? null)->filter()->values()->all();

            if ($ordered !== []) {
                self::set($role, $ordered);
            }
        }
    }

    /**
     * Roles whose account may be a group: the report adds up the group's sub-accounts as well (the cash book of `1100 Cash In Hand`
     * takes `1110 Cash`, the ageing of a receivables group takes every debtor under it).
     */
    public const GROUP_ROLES = ['cash', 'bank', 'aging.receivables', 'aging.payables'];

    /**
     * The roles with their accounts, for the Account roles screen and API.
     *
     * @return list<array{role: string, label: string, multiple: bool, groups: bool, config: string|null, overridden: bool, accounts: list<array{id: int, account_code: string, account_name: string}>}>
     */
    public static function overview(): array
    {
        $rows = [];

        foreach (self::registry() as $role => $entry) {
            $codes = self::codes($role);
            $accounts = ChartOfAccount::query()->whereIn('account_code', $codes)->get(['id', 'account_code', 'account_name'])->keyBy('account_code');
            $configured = $entry['config'] === null ? null : config($entry['config']);

            $rows[] = [
                'role' => $role, 'label' => $entry['label'], 'multiple' => $entry['multiple'], 'groups' => in_array($role, self::GROUP_ROLES, true), 'config' => $entry['config'],
                'overridden' => $configured !== null && $configured !== '' && $configured !== [],
                'accounts' => collect($codes)->map(fn (string $code) => $accounts->get($code))->filter()->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name])->values()->all(),
            ];
        }

        return $rows;
    }

    /**
     * Statement line of each seeded account, for the recommended report mapping of the company's chart.
     *
     * @return array<string, string>
     */
    public static function recommendedLines(): array
    {
        return self::preset() === 'erpnext' ? ErpNextChart::lines() : [];
    }

    /**
     * @return list<string>
     */
    private static function saved(string $role): array
    {
        $store = self::store();
        $key = CurrentCompany::currentId().'|'.$role;

        if ($store->offsetExists($key)) {
            return $store[$key];
        }

        try {
            if (! isset($store['_table'])) {
                $store['_table'] = Schema::hasTable('accounting_account_roles');
            }

            $codes = $store['_table']
                ? AccountRole::query()->where('role', $role)->orderBy('position')->with('account:id,account_code')->get()->map(fn (AccountRole $row) => $row->account?->account_code)->filter()->values()->all()
                : [];
        } catch (\Throwable) {
            $codes = [];
        }

        return $store[$key] = $codes;
    }

    /**
     * Roles are read once per request: a per-application store, emptied whenever a role is saved.
     */
    private static function store(): \ArrayObject
    {
        return app()->bound('accounting.account-roles') ? app('accounting.account-roles') : new \ArrayObject;
    }

    public static function flush(): void
    {
        self::store()->exchangeArray([]);
    }
}
