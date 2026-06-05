# Changelog

All notable changes to `laravel-chart-of-accounts` will be documented in this file.

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
