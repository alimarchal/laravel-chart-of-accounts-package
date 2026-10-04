# Changelog

All notable changes to `laravel-chart-of-accounts` will be documented in this file.

## [2.5.0] - 2026-10-07

Voucher numbering release.

### Added
- **Voucher types** per company — Journal (JV), Cash Payment (CPV), Cash Receipt (CRV), Bank Payment (BPV),
  Bank Receipt (BRV) seeded; add your own. Each has a prefix, a number format (`{PREFIX}-{FY}-{SEQ:5}` →
  `JV-2026-00012`; tokens `{PREFIX}` `{FY}` `{YYYY}` `{YY}` `{MM}` `{SEQ:n}`) and a restart rule (fiscal year,
  month, never). Fiscal-year labels follow the company's start month (`2025-26`).
- **Gapless voucher numbers issued at posting**, inside the posting transaction under a row lock: posting
  order, no duplicates under concurrency, a failed posting gives its number back, voided drafts leave no gap.
  Reversals are numbered in the series of the entry they reverse.
- Voucher numbers and types of posted entries are immutable at the database level (MySQL/MariaDB,
  PostgreSQL, SQLite); numbers are unique per company.
- Voucher Types screen (React and Blade) with next-number preview, live format example and validation;
  voucher type picker in the journal form; voucher number column, filter and page titles in journal screens.
- API: `/voucher-types` CRUD, `/voucher-types/{id}/next-number`; journal entries accept `voucher_type_id` /
  `voucher_type_code`, return `voucher_number` and `voucher_type`, filter by `voucher_number` and
  `voucher_type_id`, sort by `voucher_number`.
- Permissions `voucher-types.view/create/update/delete` (admin manages; accountant, auditor, viewer view).
- Upgrade: existing posted entries are numbered as JV in posting order and the series continues from there.

### Fixed
- Apps with an older published `config/accounting.php` did not see screens and buttons of permissions
  added by later versions (the shared permission list now always includes the package's own).
- Journal list (React) showed the raw ISO timestamp as the date.

## [2.4.0] - 2026-10-06

Chart of accounts guards at the database layer.

### Added
- **Database triggers for the chart of accounts** on MySQL/MariaDB, PostgreSQL and SQLite, so the rules hold
  even for raw SQL, imports and other apps writing to the same database: a parent must be a group account of
  the same type and company; no cycles; type, normal balance and group flag are locked once an account has
  journal lines; a group with children stays a group and keeps its children's type; accounts never move to
  another company; journal lines use accounts of their entry's company; an entry using a group account cannot
  be posted. Existing installs get them with `php artisan migrate`.

### Changed
- Saving a journal entry (API, React, Blade, simple-entry endpoint) rejects group accounts with a `422`
  instead of failing later at posting.
- `JournalEntryService` rejects accounts of another company with an `AccountingException` before writing.

## [2.3.0] - 2026-10-05

Month-end and year-end close release.

### Added
- **Close workspace** (React and Blade): a checklist before closing a period — entries waiting for approval,
  drafts in the period, trial balance balanced, earlier periods closed, bank lines reconciled, retained
  earnings account set up — each linking to the screen that fixes it. Blocking items disable the close button.
  API: `GET /periods/{id}/close-checklist[?year_end=1]`.
- **Year-end close preview**: the closing entry is shown before it is posted.
- **Monthly periods**: twelve months for a fiscal year in one click / `POST /periods/generate-monthly`; the
  fiscal-year end month is marked in the periods list.
- New periods list (React) with status, net income and Month-end / Year-end close actions.
- Reopening asks for a reason (kept in the audit trail; optional `reason` in the API).

### Fixed
- **Year-end close with monthly periods** only zeroed the last month's income and expenses. It now uses every
  income-statement balance up to the year end (net of earlier closing entries), so monthly and yearly periods
  both close the whole year — and any earlier unclosed years are swept to retained earnings as well.
- Reopening a year-end closed period left its closing entry in place, so closing again double-counted. The
  closing entry is now reversed on reopen and the period's closing figures are cleared.

### Changed
- Year-end close requires earlier periods to be closed; periods are reopened newest first.

## [2.2.0] - 2026-10-05

Multi-company release.

### Added
- **Multi-company** (`ACCOUNTING_MULTI_COMPANY=true`): any number of companies in one database, each with
  its own chart of accounts, periods, journal, cost centers, bank accounts, reconciliations, tax codes,
  snapshots, reports and audit trail. Account types and currencies are shared.
- Company access per user (`accounting_company_user`); `super-admin` works in every company. Company
  switcher on the React dashboard (`<CompanySwitcher />` for your sidebar) and above every Blade page;
  `X-Company` header (code or id) for the API; `404` for unknown and `403` for inaccessible companies.
- **Companies** screen (React) and API (`/companies`, `/companies/{id}/users`) to create companies, edit
  them and manage who may use them — changes are audited. New permission `companies.manage` (admin).
- `php artisan accounting:create-company` — creates a company with its own chart of accounts, fiscal year
  (`--fiscal-start` month), cost centers and tax codes; `--user` grants access.
- **Consolidated reports** (`reports.consolidated.view`): trial balance, balance sheet and income
  statement for several companies side by side with a group total (React page and
  `/reports/consolidated/{report}`); zero balances hidden unless `include_zero=1`.
- `--company` option on `accounting:seed`, `verify`, `health-check` and `rebuild-snapshots`; period
  commands run in the company that owns the period.
- Webhook payloads name the company of the entry or period.
- The seeded fiscal year follows the company's fiscal-year start month.

### Security / integrity
- Every company-owned query is scoped: Eloquent global scope, company filters on every report and raw
  query, and company-aware `exists`/`unique` validation rules (ids and codes of another company are
  rejected). Records of another company return 404.
- A journal line can never use another company's account or cost center (checked when posting).
- `company_id` is not mass-assignable and never changes; a posted entry cannot be moved to another
  company (database trigger on MySQL/MariaDB, PostgreSQL and SQLite). Audit rows record their company.

### Fixed
- Permissions and roles introduced by a new version were not created when the app had published an older
  `config/accounting.php` — the seeder now always includes the package's own permission list and grants.
- A command that switches company no longer leaves that company active for the rest of the process.

### Upgrading
```bash
composer update alimarchal/laravel-chart-of-accounts
php artisan accounting:update        # moves existing data into the default company "MAIN", adds the new permissions
```
Nothing changes for single-company installs. Codes that were unique across the database (account codes,
cost centers, tax codes, bank account numbers, idempotency keys) are now unique per company.

## [2.1.0] - 2026-10-04

Enterprise controls release: maker-checker, segregation-of-duties roles, events and webhooks, base-currency
reporting, and a stress-tested report and export layer. No breaking change for the ledger itself; see
**Upgrading** for the API response changes.

### Added
- **Maker-checker approvals** (`ACCOUNTING_APPROVALS_ENABLED`, `ACCOUNTING_APPROVAL_THRESHOLD` in base currency):
  submit → approve (posts) / reject with reason; the checker can never be the maker or submitter
  (`ACCOUNTING_ALLOW_SELF_APPROVAL` to opt out). API `POST /journal-entries/{id}/submit|approve|reject`,
  `filter[approval_status]`; Approve/Reject buttons in the React and Blade UIs.
- **Audit trail** on the journal entry page: who created, submitted, approved/rejected and posted it, and when.
- **Roles for segregation of duties**: new `approver` (checker) and `auditor` (read-only incl. audit log);
  `accountant` no longer manages settings; `admin` manages users only. `php artisan accounting:roles`
  prints the matrix and fails if a role can both create and approve.
- **Domain events** for every ledger action, dispatched after commit, all implementing `AccountingEvent`.
- **Signed webhooks** (`ACCOUNTING_WEBHOOK_URLS`, `ACCOUNTING_WEBHOOK_SECRET`): HMAC-SHA256 signature with
  timestamp, one queued job per endpoint with backoff, stable event id for de-duplication.
- **Base-currency amounts** frozen per line at posting (`base_debit` / `base_credit`, cents-exact with the
  rounding residual on the largest line); every report and balance now uses them. Existing posted lines are
  backfilled by the migration.
- **Account statement** with opening balance, running balance (any page) and closing balance — web page with an
  account picker, paginated API.
- **Exports**: XLSX and PDF row limits (`ACCOUNTING_EXPORT_MAX_XLSX_ROWS` / `_PDF_ROWS`), bank-book and
  cash-book exports, readable multi-page PDF.
- React: real General Ledger page (filters, table, pagination), shared `useAccounting()` data (permissions,
  flash messages, approval settings), searchable selects, pagination component.
- Installer checks the user model and prints the exact traits to add; `accounting:update` also adds roles and
  permissions introduced by new versions.
- `docs/performance.md`: stress test with 400,000 journal lines; maker-checker diagram; Postman collection updated.
- CI job against Laravel 14 (`dev-master`, PHP 8.4), allowed to fail until 14.0 and its test tooling ship.

### Changed
- `GET /reports/account-statement` and `GET /reports/cash-flow` are **paginated** (Laravel paginator + `totals`
  for the whole filter; `?per_page=`); the statement adds `account`, `opening_balance` and `running_balance`, and
  returns 404 for an unknown account.
- Posting takes a **shared** lock on the accounting period (closing/reopening takes an exclusive one):
  concurrent postings no longer serialise — about 3.5× more throughput under load.
- Trial balance totals reuse the loaded rows (one ledger scan instead of two).
- CSV exports stream from a database cursor (constant memory at any size).
- React pages are formatted and lint-clean for the current Laravel React starter kit (Inertia 3).

### Fixed
- Exports checked the permission *after* loading the data, and loaded entire ledgers into memory.
- XLSX column letters broke after column Z; PDF exports were a single unreadable line cut at 12,000 characters.
- The React export buttons used Inertia links, so downloads never started; bank/cash book exports returned 404.
- The web account statement without an account loaded every posted line; the React page had no account picker.
- Bank/cash book pages showed a running balance that was always 0.00; "Currencys" page title.
- Webhooks: a retry re-sent to endpoints that had already succeeded, and the event id changed on each retry.
- Livewire 4: `accounting::` component names failed to resolve (`ComponentNotFoundException`).
- Blade journal list: the void filter never matched; date filters were rejected; audit log filters fixed.
- Re-running the permission seeder no longer overwrites role customisations (it only adds new permissions).
- The installer crashed when the user model lacked `HasRoles`; the Sanctum trait check never fired.
- Flaky factory codes on MySQL/PostgreSQL.

### Removed
- Seven unused Livewire report components (`accounting.reports.general-ledger`, `trial-balance`,
  `balance-sheet`, `income-statement`, `cash-flow`, `bank-book`, `cash-book`). No package view used them and
  they rendered fields the reports do not return. The Blade report pages are unchanged; the aged
  receivables/payables components remain.

### Upgrading
```bash
composer update alimarchal/laravel-chart-of-accounts
php artisan accounting:update        # migrations (approval columns, base amounts + backfill), triggers,
                                     # React pages, and the new approver/auditor roles (keeps your role changes)
```
API clients of `reports/account-statement` and `reports/cash-flow` should read `data` as one page and use
`totals`, or pass `per_page` (max `ACCOUNTING_API_MAX_PER_PAGE`).

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
- Test suite runs inside the package (Orchestra Testbench): **170 tests** covering security, ledger integrity,
  reports and seeders; CI matrix for PHP 8.2–8.4 × Laravel 11–13, plus MySQL, MariaDB and PostgreSQL jobs.
- Larastan (level 5) and Pint in CI.

### Changed
- **Dropped Laravel 10** (end-of-life; models use the `casts()` method, which Laravel 10 ignores, so it never worked correctly).
- `composer.lock` is no longer committed (library packages should not ship a lock file).
- jQuery bundled asset upgraded 3.5.1 → 3.7.1.

### Added — API & developer experience
- Journal lines accept `account_code` / `cost_center_code`; entries accept `currency_code`.
- `POST /journal-entries/simple` — two-line entry by account codes.
- `Idempotency-Key` header on journal creation: safe retries, no double posting.
- Report endpoints (trial balance, balance sheet, income statement, general ledger, cash flow, bank/cash book,
  aged AR/AP, account statement), `/chart-of-accounts/tree`, `/chart-of-accounts/{id}/balance`,
  `/periods/{id}/close|reopen|close-fiscal-year`, `/health`.
- API rate limiting (`ACCOUNTING_API_RATE_LIMIT`) and a `per_page` cap (`ACCOUNTING_API_MAX_PER_PAGE`).
- `ACCOUNTING_UI_DRIVER=api` — API-only mode that loads no web routes, views or Livewire.
- `docs/openapi.yaml` (OpenAPI 3.1) and `docs/postman_collection.json`; a test fails if a route is undocumented.
- Visual guide: architecture, installation, journal lifecycle, posting checks, period close and API request
  diagrams in `docs/images/` (sources in `docs/diagrams/`), embedded in the README.
- Bank accounts can be linked to a posting GL account through the API.

### Fixed — installation
- `accounting:install`, `accounting:update` and `accounting:seed` failed in production (missing `--force`).
- `accounting:install` did not publish the React pages, so the Inertia UI could not render.
- `accounting:update` overwrote `config/accounting.php` and customised views; views are no longer published by default.
- Tax rates: duplicate (tax code, start date) returned a 500; now a validation error.
- More than one base currency could exist; the base currency can no longer change after entries are posted.

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
