<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Feature;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Switches for the optional modules and for the sub-features of payroll. A switch is the row in accounting_features if one exists,
 * otherwise the config default (accounting.features.disabled): a core feature (chart of accounts,
 * journal entries, periods, reports, users) cannot be turned off. A feature is only on while its parent is on.
 *
 * Turning a feature off hides its screens and menus and answers its web and API routes with 404; its data stays untouched, and turning
 * it on again brings everything back. Bound as a scoped singleton: the rows are read once per request.
 */
class FeatureManager
{
    /** @var array<string, bool>|null */
    private ?array $stored = null;

    /**
     * Every switch: label, description, group, the parent it depends on, and the route paths it owns (relative to the web or API prefix;
     * the most specific pattern wins, so a payroll sub-feature takes its paths from payroll itself).
     *
     * @return array<string, array{label: string, description: string, group: string, parent: string|null, paths: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            'parties' => ['label' => 'Customers & suppliers', 'description' => 'Parties, invoices and bills, payments, allocations, receivables and ageing.', 'group' => 'Modules', 'parent' => null, 'paths' => ['parties*', 'party-documents*', 'party-payments*', 'party-allocations*', 'receivables*', 'reports/aged-receivables*', 'reports/aged-payables*']],
            'tax' => ['label' => 'Tax engine', 'description' => 'Tax codes and rates, taxed entries, tax returns and withholding.', 'group' => 'Modules', 'parent' => null, 'paths' => ['tax*', 'tax-codes*', 'tax-rates*']],
            'banking' => ['label' => 'Bank reconciliation & statement import', 'description' => 'Bank accounts, reconciliations and imported bank statements with matching.', 'group' => 'Modules', 'parent' => null, 'paths' => ['bank-accounts*', 'bank-statements*', 'bank-statement-lines*', 'reconciliations*']],
            'budgets' => ['label' => 'Budgets', 'description' => 'Budget versus actual by account, cost center and period.', 'group' => 'Modules', 'parent' => null, 'paths' => ['budgets*']],
            'fixed_assets' => ['label' => 'Fixed assets', 'description' => 'Asset register, depreciation runs and disposals.', 'group' => 'Modules', 'parent' => null, 'paths' => ['fixed-assets*']],
            'inventory' => ['label' => 'Inventory', 'description' => 'Items, warehouses, stock movements and valuation.', 'group' => 'Modules', 'parent' => null, 'paths' => ['inventory*']],
            'recurring_entries' => ['label' => 'Recurring entries', 'description' => 'Recurring journal entry templates and the daily scheduler that generates them.', 'group' => 'Modules', 'parent' => null, 'paths' => ['recurring-entries*']],
            'fx_revaluation' => ['label' => 'Foreign-currency revaluation', 'description' => 'Revalue foreign-currency balances at period end.', 'group' => 'Modules', 'parent' => null, 'paths' => ['fx-revaluation*']],
            'fbr' => ['label' => 'FBR sales-tax integration', 'description' => 'Send sales invoices and credit notes to FBR (also needs accounting.fbr.enabled).', 'group' => 'Modules', 'parent' => null, 'paths' => ['fbr*']],
            'attachments' => ['label' => 'Attachments', 'description' => 'Evidence files on journal entries.', 'group' => 'Modules', 'parent' => null, 'paths' => ['attachments*']],
            'report_mapping' => ['label' => 'Statement layout & account mapping', 'description' => 'Design the financial statements and map accounts to their lines.', 'group' => 'Modules', 'parent' => null, 'paths' => ['report-mapping*', 'report-lines*']],
            'chart_templates' => ['label' => 'Chart templates', 'description' => 'Industry chart-of-accounts templates.', 'group' => 'Modules', 'parent' => null, 'paths' => ['chart-templates*']],
            'control_accounts' => ['label' => 'Control accounts', 'description' => 'Mark sub-ledger control accounts and their types.', 'group' => 'Modules', 'parent' => null, 'paths' => ['control-accounts*', 'chart-of-accounts/*/control-type']],
            'snapshots' => ['label' => 'Balance snapshots', 'description' => 'Stored account balance snapshots for fast reports.', 'group' => 'Modules', 'parent' => null, 'paths' => ['account-balance-snapshots*']],
            'audit_log' => ['label' => 'Audit log screen', 'description' => 'Browse the audit trail (the trail itself is always written).', 'group' => 'Modules', 'parent' => null, 'paths' => ['audit-logs*']],
            'payroll' => ['label' => 'Payroll', 'description' => 'Employees, pay components, monthly runs and payslips.', 'group' => 'Modules', 'parent' => null, 'paths' => ['payroll*']],
            'payroll_grades' => ['label' => 'Salary grades, bulk changes & salary history', 'description' => 'Grades, bulk assignment and raises, and the dated salary revisions.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/grades*', 'payroll/bulk*', 'payroll/revisions*', 'payroll/employees/*/revisions*']],
            'payroll_arrears' => ['label' => 'Arrears', 'description' => 'Back pay of late raises, paid with a payroll run.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/arrears*']],
            'payroll_attendance' => ['label' => 'Attendance, leave & overtime', 'description' => 'Absent days, leave types and balances, overtime hours; unpaid days come off the salary.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/attendance*', 'payroll/leave-types*', 'payroll/leaves*']],
            'payroll_loans' => ['label' => 'Loans & advances', 'description' => 'Loans and salary advances recovered by instalments from the salary.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/loans*']],
            'payroll_contributions' => ['label' => 'Contributions (EOBI, PESSI, provident fund)', 'description' => 'Employee and employer contribution schemes.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/schemes*']],
            'payroll_bank_file' => ['label' => 'Bank salary file', 'description' => 'CSV or Excel salary file for the bank.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/runs/*/bank-file/*']],
            'payroll_payslip_mail' => ['label' => 'Payslip e-mail', 'description' => 'E-mail payslips to the employees.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/runs/*/email-payslips', 'payroll/runs/*/payslips/*/email']],
            'payroll_settlements' => ['label' => 'Final settlements', 'description' => 'Gratuity, unused leave and loans owed when an employee leaves.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/settlements*']],
            'payroll_reports' => ['label' => 'Payroll reports & salary tax', 'description' => 'Month comparison, cost centers, headcount, tax statement and certificates.', 'group' => 'Payroll', 'parent' => 'payroll', 'paths' => ['payroll/reports*', 'payroll/tax*']],
        ];
    }

    public function known(string $feature): bool
    {
        return array_key_exists($feature, self::catalog());
    }

    /**
     * On when its own switch is on and so is its parent's. A name that is not a feature is on (nothing to switch).
     */
    public function enabled(string $feature): bool
    {
        $entry = self::catalog()[$feature] ?? null;

        if ($entry === null) {
            return true;
        }

        return $this->own($feature) && ($entry['parent'] === null || $this->enabled($entry['parent']));
    }

    /**
     * The switch itself, ignoring the parent.
     */
    public function own(string $feature): bool
    {
        return $this->stored()[$feature] ?? $this->default($feature);
    }

    public function default(string $feature): bool
    {
        return ! $this->configuredOff($feature);
    }

    /**
     * Every feature with its state, for the settings screen and the API.
     *
     * @return list<array{key: string, label: string, description: string, group: string, parent: string|null, enabled: bool, own: bool, default: bool, source: string}>
     */
    public function all(): array
    {
        $stored = $this->stored();
        $rows = [];

        foreach (self::catalog() as $key => $entry) {
            $rows[] = [
                'key' => $key, 'label' => $entry['label'], 'description' => $entry['description'], 'group' => $entry['group'], 'parent' => $entry['parent'],
                'enabled' => $this->enabled($key), 'own' => $this->own($key), 'default' => $this->default($key), 'source' => array_key_exists($key, $stored) ? 'database' : 'config',
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, bool> key => effective state, for the screens' menus
     */
    public function states(): array
    {
        return collect(array_keys(self::catalog()))->mapWithKeys(fn (string $key): array => [$key => $this->enabled($key)])->all();
    }

    public function set(string $feature, bool $enabled): void
    {
        if (! $this->known($feature)) {
            throw new AccountingException("'{$feature}' is not a feature that can be switched.");
        }

        $before = $this->own($feature);
        $row = Feature::query()->updateOrCreate(['feature' => $feature], ['enabled' => $enabled, 'updated_by' => Auth::id()]);
        $this->stored = null;

        if ($before !== $enabled) {
            AccountingAuditLog::record($row, $enabled ? 'FEATURE_ENABLED' : 'FEATURE_DISABLED', ['enabled' => $before], ['enabled' => $enabled], ['feature' => $feature]);
        }
    }

    /**
     * Drop the override: the feature follows the config default again.
     */
    public function reset(string $feature): void
    {
        Feature::query()->where('feature', $feature)->delete();
        $this->stored = null;
    }

    /**
     * The feature that owns a route path (relative to the web or API prefix), or null for the core.
     */
    public function forPath(string $path): ?string
    {
        $path = trim($path, '/');
        $best = null;
        $bestLength = -1;

        foreach (self::catalog() as $key => $entry) {
            foreach ($entry['paths'] as $pattern) {
                if (strlen($pattern) > $bestLength && Str::is($pattern, $path)) {
                    $best = $key;
                    $bestLength = strlen($pattern);
                }
            }
        }

        return $best;
    }

    /**
     * A route uri or url path without the web or API prefix ("accounting/payroll/loans" becomes "payroll/loans").
     */
    public function relative(string $uri): string
    {
        $path = trim($uri, '/');

        foreach ([(string) config('accounting.api_prefix', 'api/v1/accounting'), (string) config('accounting.route_prefix', 'accounting')] as $prefix) {
            $prefix = trim($prefix, '/');

            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return trim(substr($path, strlen($prefix)), '/');
            }
        }

        return $path;
    }

    /**
     * False when the screen or endpoint at this uri or url belongs to a feature that is switched off.
     */
    public function pathEnabled(string $uriOrUrl): bool
    {
        $feature = $this->forPath($this->relative((string) (parse_url($uriOrUrl, PHP_URL_PATH) ?? $uriOrUrl)));

        return $feature === null || $this->enabled($feature);
    }

    /**
     * Glob patterns (with the web prefix) of every screen that is switched off, for the React menus.
     *
     * @return list<string>
     */
    public function disabledPatterns(): array
    {
        $prefix = '/'.trim((string) config('accounting.route_prefix', 'accounting'), '/');
        $patterns = [];

        foreach (self::catalog() as $key => $entry) {
            if (! $this->enabled($key)) {
                foreach ($entry['paths'] as $pattern) {
                    $patterns[] = $prefix.'/'.$pattern;
                }
            }
        }

        return $patterns;
    }

    /**
     * @return array<string, bool>
     */
    private function stored(): array
    {
        if ($this->stored !== null) {
            return $this->stored;
        }

        try {
            return $this->stored = Schema::hasTable('accounting_features') ? Feature::query()->pluck('enabled', 'feature')->map(fn ($value): bool => (bool) $value)->all() : [];
        } catch (\Throwable) {
            return $this->stored = [];
        }
    }

    private function configuredOff(string $feature): bool
    {
        return in_array($feature, array_map('trim', (array) config('accounting.features.disabled', [])), true);
    }
}
