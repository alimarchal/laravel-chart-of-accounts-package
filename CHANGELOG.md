# Changelog

All notable changes to `laravel-chart-of-accounts` will be documented in this file.

## [1.3.0] - 2026-06-04

### Added
- **`JournalEntry::record()`** — static helper to create and optionally post a balanced GL entry in one line:
  ```php
  JournalEntry::record(
      description: 'Salary payment',
      debitAccountCode: '6101',
      creditAccountCode: '1101',
      amount: 50000,
      post: true,
  );
  ```
- **Select2 integration** — all `<select>` elements across all Blade/Livewire views now use Select2 with Tailwind-matched styling (same pattern as moontraders)
- **Local public assets** — jQuery 3.5.1 and Select2 4.1.0 served from `public/vendor/accounting/` (no CDN dependency); falls back to CDN if assets not published
- **`accounting-assets` publish tag** — `php artisan vendor:publish --tag=accounting-assets` copies jQuery + Select2 to `public/vendor/accounting/`
- **`accounting:install`** now publishes public assets automatically (no manual step needed)
- **`accounting:update`** now re-publishes public assets with `--force`
- Label-click opens Select2 dropdown (matches moontraders UX pattern)

### Fixed
- **Journal entry balance enforcement** — `save()` now blocks submission if `|debits − credits| ≥ 0.01`, showing a clear error message with the difference amount
- **Dashboard grid** — changed from `lg:grid-cols-4` to `lg:grid-cols-3` (3 cards per row)
- **`app-layout`** — Select2 CSS/JS now injected into host layout via `@push('styles')` / `@push('scripts')` stacks; standalone fallback uses its own `<head>`

## [1.2.1] - 2026-06-03

### Fixed
- `account-balances.blade.php` had duplicate content after `</x-accounting::app-layout>` causing `ParseError: syntax error, unexpected token "endif", expecting end of file`

## [1.2.0] - 2026-06-03

### Fixed
- `JournalEntryBladeController::show()` was passing `entry` to the view but the Blade template expected `$journalEntry`, causing `Undefined variable $journalEntry` on `/accounting/journal-entries/{id}`

### Added
- `accounting:install` now automatically publishes views during installation
- New `accounting:update` command: re-publishes views, config, and JS assets, runs migrations, syncs DB objects

## [1.1.0] - 2026-06-03

### Fixed
- Package is now **fully self-contained** — no more `App\` namespace dependencies
- Fixed `App\Models\User` references — now uses `config('auth.providers.users.model')`
- Added Laravel 13 support

### Added
- `spatie/laravel-activitylog`, `spatie/laravel-permission`, `spatie/laravel-query-builder` as proper `require` dependencies
- Comprehensive README

## [1.0.0] - 2026-05-29

### Added
- Initial release — double-entry bookkeeping, chart of accounts, multi-currency, 10 financial reports, bank reconciliation, dual frontend (Inertia/React + Blade/Livewire), full REST API, 9 Artisan commands, Spatie Permission integration, accounting periods, cost centers, tax codes, audit logging, 9 model factories
