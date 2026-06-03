# Changelog

All notable changes to `laravel-chart-of-accounts` will be documented in this file.

## [1.1.0] - 2026-06-03

### Fixed
- Package is now **fully self-contained** — no more `App\` namespace dependencies
- Moved `HasUserTracking`, `LogsAccountingActivity`, `HasAccountingValidationRules` traits into the package under `Alimarchal\LaravelChartOfAccounts\Concerns`
- Fixed `App\Models\User` references in `JournalEntry`, `AccountingPeriod`, and `AccountingAuditLog` — now uses `config('auth.providers.users.model')` for compatibility with any User model
- Added Laravel 13 support (`illuminate ^13.0`)

### Added
- `spatie/laravel-activitylog ^4.7` added as a proper `require` dependency (was missing)
- `spatie/laravel-permission ^6.0` added as a proper `require` dependency (was missing)
- `spatie/laravel-query-builder ^5.0|^6.0` added as a proper `require` dependency (was missing)
- All Spatie packages now auto-install with the package — zero manual setup
- Comprehensive README with installation guide, usage examples, route table, permissions list, factory examples, and package structure

## [1.0.0] - 2026-05-29

### Added
- Initial release
- Double-entry bookkeeping with journal entries and draft → posted → void workflow
- Chart of Accounts with hierarchical tree structure
- Multi-currency support with exchange rates
- 10 financial reports (General Ledger, Trial Balance, Balance Sheet, Income Statement, Cash Flow, Aged Payables, Aged Receivables, Bank Book, Cash Book, Account Statement)
- Bank reconciliation with automated matching
- Dual frontend: Inertia/React and Blade/Livewire
- Full REST API with JSON resources and `spatie/laravel-query-builder`
- 9 Artisan commands (install, seed, sync-db-objects, verify, health-check, rebuild-snapshots, close-fiscal-year, close-period, open-period)
- Spatie Permission integration with seeded roles and permissions
- Accounting periods with open/close/fiscal year workflow
- Cost Centers and Tax Codes/Rates
- Audit logging for all accounting transactions via `spatie/laravel-activitylog`
- 9 Eloquent model factories with states
- Full database seeders for all master data
