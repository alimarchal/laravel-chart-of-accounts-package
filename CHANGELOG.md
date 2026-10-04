# Changelog

All notable changes to `laravel-chart-of-accounts` will be documented in this file.

## [2.0.0] - 2026-10-04

Production-hardening release. It fixes security vulnerabilities and accounting-correctness bugs; a few
behaviour changes are breaking — read **Upgrading** below.

### Security
- **SQL injection** in the Blade Balance Sheet, Income Statement and Account Balances reports: the
  `as_of_date` / `start_date` / `end_date` query parameters were interpolated into raw SQL. They are now
  validated (`Y-m-d`) and normalised before use.
- **Privilege escalation** in user/role management: an `admin` could assign `super-admin` (or any role or
  permission) to themselves or others, reset a super-admin's password, and settings managers could widen
  any role. New `PrivilegeGuard`: you can only grant roles/permissions you hold, only a super-admin can
  manage super-admins or the `super-admin` role, and changing roles requires `user.assign-role`.
- **Segregation of duties**: `auto_post=true` now requires `journal-entries.post` (previously
  `journal-entries.create` was enough to post).
- Period status could be changed to closed/open through the edit form, bypassing `periods.close` /
  `periods.reopen` and the close procedure. Status changes now run the close/reopen actions and require
  those permissions.
- The Livewire journal entry form now authorizes `save()` and locks `entryId`.

### Fixed — accounting correctness
- `closing_net_income` was always ≈ 0 (credits − debits of *all* accounts); it is now revenue − expenses.
- Closing a period with draft entries is blocked; posting and closing lock the period row so they cannot interleave.
- Fiscal-year close failed on income-statement accounts with a contra balance (zero-amount lines).
- Balance checks use exact integer cents (`Support\Money`) instead of floats.
- Balance snapshots now carry the opening balance (previously always 0) — closing balances were period movement only.
- Bank Book was always empty with the seeded chart (it filtered on the `1102` *group* account) and Cash Flow
  ignored all bank activity. Both now include child accounts and accounts linked to `BankAccount` records.
- Bank/Cash Book totals ignored the account filter and summed the whole ledger.
- General Ledger, Account Statement, Bank/Cash Book and Cash Flow included **draft and void** entries.
- Aged Receivables/Payables buckets overlapped (91–180 days counted twice); buckets now sum to the balance.
- Balance Sheet did not balance before year-end (unclosed earnings missing) and showed a meaningless
  "statement total"; it now adds a *Current Earnings (unclosed)* row and reports assets vs liabilities + equity.
- Income Statement (Inertia) had no date range, showed zero for a closed year (closing entries), and the
  React page *added* expenses to revenue for net income.
- Re-running `accounting:seed` reopened a closed fiscal year and overwrote user edits to accounts and
  exchange rates; seeders now only create missing records. The currency seeder honours `ACCOUNTING_BASE_CURRENCY`.
- Balance Snapshot pages crashed (non-existent `period` relation) and their filters used non-existent columns.
- React Bank/Cash Book pages read non-existent `debit_amount` / `credit_amount` columns.
- Role management routes checked non-existent `accounting.manage-settings.*` permissions (always 403).
- Deleting a record still referenced elsewhere (currency, account type, tax code, …) returned a 500; package
  routes now answer 422 / flash error. The base currency can no longer be deleted.

### Fixed — chart of accounts
- Updating an account through the API without `is_active` / `is_group` silently **deactivated** it or turned a
  group into a posting account. Absent flags are now left unchanged.
- Hierarchy cycles (an account under itself or its descendant) are rejected; the parent must be a group account
  of the same account type.
- Code, type, normal balance and group flag are locked once an account has journal lines.
- Accounts referenced by `config('accounting.defaults')` cannot have their code changed, be deactivated or deleted.
- Deleting an account with children or journal lines (and any generic resource still referenced) returns a clear
  422 / flash error instead of a 500.
- `normal_balance` defaults to the account type's (contra accounts may still override it).
- The tree is built from a single query instead of one query per level.

### Added
- Business-rule violations (`AccountingRuleViolation`) render as **HTTP 422** JSON or redirect-back-with-error.
- Database triggers on MySQL/MariaDB, PostgreSQL and SQLite make **posted journal entries and their lines immutable**.
- MySQL/MariaDB/SQLite audit triggers record full old/new row values (PostgreSQL already did).
- Accounting periods may not overlap; dates of a period with entries cannot change; such periods cannot be deleted.
- Optional `reversal_date` when reversing; reversals of reversals and back-dated reversals are rejected.
- `JournalEntry::isReversed()`, `reversed()` and `posted()` scopes; audit records for void, reverse, close and reopen.
- `ChartOfAccountService` and `AccountingPeriodService` hold all integrity rules for API, Inertia and Blade.
- Config: `chart_preset` (`general` default, `school`), `aging.*_account_codes`, `users_table`.
- `accounting:install --admin-email=` chooses who receives the super-admin role.
- Test suite runs inside the package (Orchestra Testbench): **141 tests** covering security, ledger integrity,
  reports and seeders; CI matrix for PHP 8.2–8.4 × Laravel 11–13, plus MySQL, MariaDB and PostgreSQL jobs.
- Larastan (level 5) and Pint in CI.

### Changed
- **Dropped Laravel 10** (end-of-life; models use the `casts()` method, which Laravel 10 ignores, so it never worked correctly).
- `composer.lock` is no longer committed (library packages should not ship a lock file).
- jQuery bundled asset upgraded 3.5.1 → 3.7.1.

### Upgrading from 1.x
1. `composer update alimarchal/laravel-chart-of-accounts` then `php artisan accounting:update`
   (re-publishes views/assets and re-syncs database objects — this installs the new triggers).
2. **Existing school installs**: set `ACCOUNTING_CHART_PRESET=school` so any accounts added by future seeding
   use the school names. Existing accounts are never renamed.
3. Reversed entries keep `status = posted` (both entries stay in the ledger, GAAP-style); use
   `isReversed()` / `reversed()` instead of looking for a `reversed` status.
4. Reports now exclude drafts by default; pass `status=all` to the General Ledger to see every status.
5. Clients that set a period's `status` via update need the `periods.close` / `periods.reopen` permissions.
6. API clients that relied on 500 responses for rule violations now receive 422 with a `message`.
7. If your users table is not `users`, set `ACCOUNTING_USERS_TABLE` **before** running the migrations on a new install.

## [1.4.0] - 2026-06-05

### Changed
- **Create buttons now show only the `+` icon** — text label removed from all list page headers for a cleaner, compact toolbar. Label preserved as a `title` tooltip on the button.
- **Audit logs page** migrated to `page-header` component with improved filters: Table dropdown (dynamically populated from DB), Action dropdown (INSERT/UPDATE/DELETE), Date From/To.
- **Journal entries list** header migrated to `page-header` component.
- **Aged Receivables & Aged Payables** now support an **As of Date** filter (Livewire `wire:model.live`) — changing the date instantly recalculates all aging buckets.
- Aged reports now calculate 5 proper buckets: Current (0–30 days), 1–30, 31–60, 61–90, >90 days.
- Aged report pages use `page-header` component for consistent nav/print/back buttons.

### Fixed
- `AuditLogBladeController` passes `$tableNames` to view for the table filter dropdown.
- Aged report views use correct column names (`current_balance`, `balance`, `days_over_90`) from updated queries.

## [1.3.9] - 2026-06-04

### Added
- Select2 on Journal Entry Line account and cost center dropdowns with full Livewire compatibility (destroy/reinit on `livewire:updated`)
- Real-time balance status badge on journal entry form (green = balanced, red = not balanced)
- **Save Draft button is disabled** until debits exactly equal credits — prevents accidental unbalanced saves
- Detailed balance warning row showing debit total, credit total, and difference amount

### Fixed
- Missing `grid` CSS class on all filter divs (batch script had removed it) — all 25 index/report views
- `backRoute="settings.dashboard"` → `backRoute="accounting.dashboard"` in users, roles, permissions index views
- Spatie permission cache reset added to fix intermittent 403 on roles page
- Select2 CSS updated to exact moontraders pattern: `.select2 { width: auto !important; display: block; }`

## [1.3.8] - 2026-06-04

### Fixed
- Restored missing `grid` Tailwind class removed by batch standardization script in all 25 filter views

## [1.3.7] - 2026-06-04

### Changed
- Standardized all filter sections to `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4` — max 4 inputs per row on all 25 index and report pages

## [1.3.6] - 2026-06-04

### Fixed
- `Route [settings.dashboard] not defined` on users, roles, and permissions index pages — `backRoute` was wrong
- 403 on `/settings/roles` — Spatie permission cache cleared

## [1.3.5] - 2026-06-04

### Fixed
- `Undefined variable $periods` on `/settings/account-balance-snapshots` — controller now passes `$periods` and `$accounts` to view

## [1.3.4] - 2026-06-04

### Fixed
- `Route [accounting.accounting-periods.index] not defined` on settings dashboard — correct route name is `accounting.periods.index`

## [1.3.3] - 2026-06-04

### Added
- Full moontraders-style settings dashboard with all 24+ routes grouped into 7 sections: Journal & Ledger, Financial Reports, Aging & Banking, Bank & Reconciliation, Master Data, Access & Identity, Audit
- Dashboard header changed from "Accounting Dashboard" to "Settings"
- Navigation label changed from "Accounting" to "Settings"
- Route prefix configurable via `ACCOUNTING_ROUTE_PREFIX` env variable (default: `accounting`, set to `settings` to use `/settings/` URLs)

## [1.3.2] - 2026-06-04

### Fixed
- `Undefined variable $summary` on settings dashboard — controller was passing `$counts` but view expected `$summary`
- `accounting_` table prefix for all models via `AccountingModel::getTable()` override (was using bare table names like `journal_entries`)

## [1.3.1] - 2026-06-03

### Fixed
- All accounting models now automatically prefix tables with `accounting_` via `AccountingModel::getTable()` override

## [1.3.0] - 2026-06-04

### Added
- **`JournalEntry::record()`** — static helper to create and optionally post a balanced GL entry in one line
- **Select2 integration** — all `<select>` elements across all Blade/Livewire views now use Select2 with Tailwind-matched styling
- **Local public assets** — jQuery 3.5.1 and Select2 4.1.0 served from `public/vendor/accounting/` (CDN fallback)
- **`accounting-assets` publish tag** — copies jQuery + Select2 to `public/vendor/accounting/`
- `accounting:install` now publishes public assets automatically
- `accounting:update` now re-publishes public assets with `--force`

### Fixed
- Journal entry balance enforcement — `save()` blocks submission if `|debits − credits| ≥ 0.01`
- Dashboard grid changed from `lg:grid-cols-4` to `lg:grid-cols-3`

## [1.2.1] - 2026-06-03

### Fixed
- `account-balances.blade.php` had duplicate content after `</x-accounting::app-layout>` causing `ParseError`

## [1.2.0] - 2026-06-03

### Fixed
- `JournalEntryBladeController::show()` passing `entry` instead of `journalEntry` to view

### Added
- `accounting:install` auto-publishes views
- New `accounting:update` command

## [1.1.0] - 2026-06-03

### Fixed
- Package fully self-contained — no more `App\` namespace dependencies
- Fixed `App\Models\User` references — now uses `config('auth.providers.users.model')`
- Laravel 13 support

### Added
- `spatie/laravel-activitylog`, `spatie/laravel-permission`, `spatie/laravel-query-builder` as proper dependencies

## [1.0.0] - 2026-05-29

### Added
- Initial release — double-entry bookkeeping, chart of accounts, multi-currency, 10 financial reports, bank reconciliation, dual frontend, full REST API, 9 Artisan commands, Spatie Permission RBAC, accounting periods, cost centers, tax codes, audit logging, 9 model factories
