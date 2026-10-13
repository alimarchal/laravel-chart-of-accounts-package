<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The one menu of the accounting area, in the same order for every kit (Livewire/Flux, Jetstream, Breeze, React): a single "Accounting"
 * entry in the host's menu that opens into sections (journal, sales and purchases, banking, payroll, assets, reports), then the settings
 * sections and the help centre. Each item carries the permission that lets a person see it, the feature that switches it off, the
 * screens that make it "current", and the help topic of that screen.
 *
 * The host menus only draw what this class returns, so a new screen is added here once.
 */
final class AccountingMenu
{
    /**
     * Sections: key, title, area (accounting|settings|help), icon (a name both icon sets know, see the kit components) and items.
     * Item: key (the help topic), title, route (a name relative to the route name prefix; "settings." names use the settings prefix),
     * permission (any of), match (paths relative to the prefix that make the item current).
     *
     * @return list<array<string, mixed>>
     */
    public static function definition(): array
    {
        return [
            ['key' => 'overview', 'title' => 'Overview', 'area' => 'accounting', 'icon' => 'overview', 'items' => [
                ['key' => 'overview', 'title' => 'Overview', 'route' => 'overview', 'permission' => ['reports.trial-balance.view'], 'match' => ['overview*']],
                ['key' => 'home', 'title' => 'All modules', 'route' => 'dashboard', 'permission' => ['accounting.view'], 'match' => ['']],
            ]],
            ['key' => 'journal', 'title' => 'Journal & ledger', 'area' => 'accounting', 'icon' => 'journal', 'items' => [
                ['key' => 'journal-entries', 'title' => 'Journal entries', 'route' => 'journal-entries.index', 'permission' => ['journal-entries.view'], 'match' => ['journal-entries*']],
                ['key' => 'recurring-entries', 'title' => 'Recurring entries', 'route' => 'recurring-entries.index', 'permission' => ['recurring-entries.view'], 'match' => ['recurring-entries*']],
                ['key' => 'chart-of-accounts', 'title' => 'Chart of accounts', 'route' => 'chart-of-accounts.index', 'permission' => ['chart-of-accounts.view'], 'match' => ['chart-of-accounts*', 'chart-templates*']],
                ['key' => 'account-types', 'title' => 'Account types', 'route' => 'account-types.index', 'permission' => ['account-types.view'], 'match' => ['account-types*']],
                ['key' => 'fx-revaluation', 'title' => 'Currency revaluation', 'route' => 'fx-revaluation.index', 'permission' => ['fx-revaluation.view'], 'match' => ['fx-revaluation*']],
                ['key' => 'balance-snapshots', 'title' => 'Balance snapshots', 'route' => 'account-balance-snapshots.index', 'permission' => ['account-balance-snapshots.view'], 'match' => ['account-balance-snapshots*']],
            ]],
            ['key' => 'sales', 'title' => 'Sales & purchases', 'area' => 'accounting', 'icon' => 'sales', 'items' => [
                ['key' => 'parties', 'title' => 'Customers & suppliers', 'route' => 'parties.index', 'permission' => ['parties.view'], 'match' => ['parties*']],
                ['key' => 'party-documents', 'title' => 'Invoices & bills', 'route' => 'party-documents.index', 'permission' => ['parties.view'], 'match' => ['party-documents*']],
                ['key' => 'party-payments', 'title' => 'Receipts & payments', 'route' => 'party-payments.index', 'permission' => ['parties.view'], 'match' => ['party-payments*', 'party-allocations*']],
                ['key' => 'aged-receivables', 'title' => 'Aged receivables', 'route' => 'reports.aged-receivables', 'permission' => ['reports.aged-receivables.view'], 'match' => ['reports/aged-receivables*', 'receivables*']],
                ['key' => 'aged-payables', 'title' => 'Aged payables', 'route' => 'reports.aged-payables', 'permission' => ['reports.aged-payables.view'], 'match' => ['reports/aged-payables*']],
                ['key' => 'tax', 'title' => 'Tax returns', 'route' => 'tax.index', 'permission' => ['tax-returns.view'], 'match' => ['tax']],
                ['key' => 'fbr', 'title' => 'FBR invoices', 'route' => 'fbr.index', 'permission' => ['fbr.view'], 'match' => ['fbr*']],
                ['key' => 'whatsapp', 'title' => 'WhatsApp', 'route' => 'whatsapp.index', 'permission' => ['whatsapp.view'], 'match' => ['whatsapp*']],
            ]],
            ['key' => 'banking', 'title' => 'Banking', 'area' => 'accounting', 'icon' => 'banking', 'items' => [
                ['key' => 'bank-accounts', 'title' => 'Bank accounts', 'route' => 'bank-accounts.index', 'permission' => ['bank-accounts.view'], 'match' => ['bank-accounts*']],
                ['key' => 'bank-statements', 'title' => 'Bank statements', 'route' => 'bank-statements.index', 'permission' => ['bank-statements.view'], 'match' => ['bank-statements*', 'bank-statement-lines*']],
                ['key' => 'reconciliations', 'title' => 'Reconciliations', 'route' => 'reconciliations.index', 'permission' => ['reconciliations.view'], 'match' => ['reconciliations*']],
                ['key' => 'bank-book', 'title' => 'Bank book', 'route' => 'reports.bank-book', 'permission' => ['reports.bank-book.view'], 'match' => ['reports/bank-book*']],
                ['key' => 'cash-book', 'title' => 'Cash book', 'route' => 'reports.cash-book', 'permission' => ['reports.cash-book.view'], 'match' => ['reports/cash-book*']],
            ]],
            ['key' => 'payroll', 'title' => 'Payroll', 'area' => 'accounting', 'icon' => 'payroll', 'items' => [
                ['key' => 'payroll', 'title' => 'Payroll runs', 'route' => 'payroll.index', 'permission' => ['payroll.view'], 'match' => ['payroll', 'payroll/runs*']],
                ['key' => 'payroll-employees', 'title' => 'Employees', 'route' => 'payroll.employees.index', 'permission' => ['payroll.view'], 'match' => ['payroll/employees*', 'payroll/grades*', 'payroll/components*', 'payroll/schemes*', 'payroll/bulk*']],
                ['key' => 'payroll-time', 'title' => 'Attendance & leave', 'route' => 'payroll.attendance.index', 'permission' => ['payroll.view'], 'match' => ['payroll/attendance*', 'payroll/leaves*', 'payroll/leave-types*']],
                ['key' => 'payroll-loans', 'title' => 'Loans & advances', 'route' => 'payroll.loans.index', 'permission' => ['payroll.view'], 'match' => ['payroll/loans*', 'payroll/arrears*', 'payroll/adjustments*', 'payroll/settlements*']],
                ['key' => 'payroll-reports', 'title' => 'Payroll reports & tax', 'route' => 'payroll.reports.index', 'permission' => ['payroll.view'], 'match' => ['payroll/reports*', 'payroll/tax*']],
            ]],
            ['key' => 'assets', 'title' => 'Assets & stock', 'area' => 'accounting', 'icon' => 'assets', 'items' => [
                ['key' => 'fixed-assets', 'title' => 'Fixed assets', 'route' => 'fixed-assets.index', 'permission' => ['fixed-assets.view'], 'match' => ['fixed-assets*']],
                ['key' => 'inventory', 'title' => 'Inventory', 'route' => 'inventory.index', 'permission' => ['inventory.view'], 'match' => ['inventory*']],
            ]],
            ['key' => 'reports', 'title' => 'Reports', 'area' => 'accounting', 'icon' => 'reports', 'items' => [
                ['key' => 'trial-balance', 'title' => 'Trial balance', 'route' => 'reports.trial-balance', 'permission' => ['reports.trial-balance.view'], 'match' => ['reports/trial-balance*']],
                ['key' => 'general-ledger', 'title' => 'General ledger', 'route' => 'reports.general-ledger', 'permission' => ['reports.general-ledger.view'], 'match' => ['reports/general-ledger*', 'reports/account-statement*']],
                ['key' => 'balance-sheet', 'title' => 'Balance sheet', 'route' => 'reports.balance-sheet', 'permission' => ['reports.balance-sheet.view'], 'match' => ['reports/balance-sheet*']],
                ['key' => 'income-statement', 'title' => 'Income statement', 'route' => 'reports.income-statement', 'permission' => ['reports.income-statement.view'], 'match' => ['reports/income-statement*']],
                ['key' => 'cash-flow', 'title' => 'Cash flow', 'route' => 'reports.cash-flow', 'permission' => ['reports.cash-flow.view'], 'match' => ['reports/cash-flow*']],
                ['key' => 'financial-statements', 'title' => 'Financial statements', 'route' => 'reports.financial-statements', 'permission' => ['reports.financial-statements.view'], 'match' => ['reports/financial-statements*', 'reports/consolidated*']],
                ['key' => 'account-balances', 'title' => 'Account balances', 'route' => 'reports.account-balances', 'permission' => ['reports.account-balances.view'], 'match' => ['reports/account-balances*']],
                ['key' => 'budgets', 'title' => 'Budgets', 'route' => 'budgets.index', 'permission' => ['budgets.view'], 'match' => ['budgets*']],
                ['key' => 'report-mapping', 'title' => 'Report mapping', 'route' => 'report-mapping.index', 'permission' => ['report-mapping.manage'], 'match' => ['report-mapping*']],
            ]],
            ['key' => 'access', 'title' => 'Users & access', 'area' => 'settings', 'icon' => 'access', 'items' => [
                ['key' => 'users', 'title' => 'Users', 'route' => 'settings.users.index', 'permission' => ['user.view'], 'match' => ['@settings/users*', 'users*']],
                ['key' => 'roles', 'title' => 'Roles', 'route' => 'settings.roles.index', 'permission' => ['accounting.manage-settings'], 'match' => ['@settings/roles*', 'roles*']],
                ['key' => 'permissions', 'title' => 'Permissions', 'route' => 'settings.permissions.index', 'permission' => ['accounting.manage-settings'], 'match' => ['@settings/permissions*', 'permissions*']],
                ['key' => 'audit-logs', 'title' => 'Audit log', 'route' => 'audit-logs.index', 'permission' => ['audit-logs.view'], 'match' => ['audit-logs*']],
            ]],
            ['key' => 'setup', 'title' => 'Set-up', 'area' => 'settings', 'icon' => 'setup', 'items' => [
                ['key' => 'periods', 'title' => 'Accounting periods', 'route' => 'periods.index', 'permission' => ['periods.view'], 'match' => ['periods*']],
                ['key' => 'currencies', 'title' => 'Currencies', 'route' => 'currencies.index', 'permission' => ['currencies.view'], 'match' => ['currencies*']],
                ['key' => 'cost-centers', 'title' => 'Cost centers', 'route' => 'cost-centers.index', 'permission' => ['cost-centers.view'], 'match' => ['cost-centers*']],
                ['key' => 'branches', 'title' => 'Branches', 'route' => 'branches.index', 'permission' => ['branches.view'], 'match' => ['branches*']],
                ['key' => 'voucher-types', 'title' => 'Voucher types', 'route' => 'voucher-types.index', 'permission' => ['voucher-types.view'], 'match' => ['voucher-types*']],
                ['key' => 'tax-codes', 'title' => 'Tax codes', 'route' => 'tax-codes.index', 'permission' => ['tax-codes.view'], 'match' => ['tax-codes*']],
                ['key' => 'tax-rates', 'title' => 'Tax rates', 'route' => 'tax-rates.index', 'permission' => ['tax-rates.view'], 'match' => ['tax-rates*']],
                ['key' => 'control-accounts', 'title' => 'Control accounts', 'route' => 'control-accounts.index', 'permission' => ['chart-of-accounts.view'], 'match' => ['control-accounts*']],
                ['key' => 'account-roles', 'title' => 'Account roles', 'route' => 'settings.account-roles.index', 'permission' => ['accounting.manage-settings'], 'match' => ['@settings/account-roles*', 'account-roles*']],
                ['key' => 'features', 'title' => 'Modules on / off', 'route' => 'settings.features.index', 'permission' => ['accounting.manage-settings'], 'match' => ['@settings/features*', 'features*']],
            ]],
            ['key' => 'help', 'title' => 'Help', 'area' => 'help', 'icon' => 'help', 'items' => [
                ['key' => 'help', 'title' => 'Help centre', 'route' => 'help.index', 'permission' => ['accounting.view'], 'match' => ['help*']],
            ]],
        ];
    }

    /**
     * The menu for one person on one request: only what they may open, what is switched on and exists, with the current item marked
     * (and, for the module cards of the hub pages, the one-line summary of each item's help article).
     *
     * @return list<array{key: string, title: string, area: string, icon: string, open: bool, items: list<array{key: string, title: string, url: string, active: bool, help: string, summary?: string}>}>
     */
    public static function for(?Authenticatable $user, ?string $path = null, bool $summaries = false): array
    {
        $features = app(FeatureManager::class);
        $name = trim((string) config('accounting.route_name_prefix', 'accounting'), '.');
        $settingsName = trim((string) config('accounting.settings_route_name_prefix', 'settings'), '.');
        $webPrefix = trim((string) config('accounting.route_prefix', 'accounting'), '/');
        $settingsPrefix = trim((string) config('accounting.settings_route_prefix', 'settings'), '/');
        $path = trim($path ?? request()->path(), '/');
        $relative = $path === $webPrefix ? '' : (str_starts_with($path, $webPrefix.'/') ? substr($path, strlen($webPrefix) + 1) : null);
        $settingsRelative = str_starts_with($path, $settingsPrefix.'/') ? substr($path, strlen($settingsPrefix) + 1) : null;
        $sections = [];

        foreach (self::definition() as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                // The Blade screens of users, roles and so on are under the settings prefix; the React ones under the accounting prefix.
                $candidates = str_starts_with($item['route'], 'settings.')
                    ? [$settingsName.substr($item['route'], strlen('settings')), $name.substr($item['route'], strlen('settings')), 'accounting'.substr($item['route'], strlen('settings'))]
                    : [$name.'.'.$item['route'], 'accounting.'.$item['route']];
                $routeName = collect($candidates)->first(fn (string $candidate): bool => Route::has($candidate));

                if ($routeName === null || ! self::allowed($user, $item['permission'])) {
                    continue;
                }

                $url = route($routeName, absolute: false);

                if (! $features->pathEnabled($url)) {
                    continue;
                }

                $active = false;

                foreach ($item['match'] as $pattern) {
                    $settingsPattern = str_starts_with($pattern, '@settings/');
                    $subject = $settingsPattern ? $settingsRelative : $relative;
                    $glob = $settingsPattern ? substr($pattern, strlen('@settings/')) : $pattern;

                    if ($subject !== null && ($glob === '' ? $subject === '' : Str::is($glob, $subject))) {
                        $active = true;
                        break;
                    }
                }

                $entry = ['key' => $item['key'], 'title' => $item['title'], 'url' => $url, 'active' => $active, 'help' => $item['key']];

                if ($summaries) {
                    $entry['summary'] = app(Help::class)->topic($item['key'])['summary'] ?? '';        // the one line of its help article, for the module cards
                }

                $items[] = $entry;
            }

            if ($items !== []) {
                $sections[] = ['key' => $section['key'], 'title' => $section['title'], 'area' => $section['area'], 'icon' => $section['icon'], 'open' => collect($items)->contains('active', true), 'items' => $items];
            }
        }

        return $sections;
    }

    /** @param  list<string>  $permissions  any of them (empty: everybody who is signed in) */
    private static function allowed(?Authenticatable $user, array $permissions): bool
    {
        if ($permissions === []) {
            return $user !== null;
        }

        if ($user === null || ! method_exists($user, 'can')) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The help topic of the screen a person is on (the key of the item whose screens match the path), or null.
     */
    public static function topicFor(string $path): ?string
    {
        $webPrefix = trim((string) config('accounting.route_prefix', 'accounting'), '/');
        $settingsPrefix = trim((string) config('accounting.settings_route_prefix', 'settings'), '/');
        $path = trim($path, '/');
        $relative = $path === $webPrefix ? '' : (str_starts_with($path, $webPrefix.'/') ? substr($path, strlen($webPrefix) + 1) : null);
        $settingsRelative = str_starts_with($path, $settingsPrefix.'/') ? substr($path, strlen($settingsPrefix) + 1) : null;
        $best = null;
        $bestLength = -1;

        foreach (self::definition() as $section) {
            foreach ($section['items'] as $item) {
                foreach ($item['match'] as $pattern) {
                    $settingsPattern = str_starts_with($pattern, '@settings/');
                    $subject = $settingsPattern ? $settingsRelative : $relative;
                    $glob = $settingsPattern ? substr($pattern, strlen('@settings/')) : $pattern;

                    if ($subject !== null && ($glob === '' ? $subject === '' : Str::is($glob, $subject)) && strlen($glob) > $bestLength) {
                        $best = $item['key'];
                        $bestLength = strlen($glob);
                    }
                }
            }
        }

        return $best;
    }
}
