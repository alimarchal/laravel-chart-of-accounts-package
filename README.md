# Laravel Chart of Accounts

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![Total Downloads](https://img.shields.io/packagist/dt/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![License](https://img.shields.io/packagist/l/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)

A comprehensive **Chart of Accounts** and full double-entry **Accounting module** for Laravel applications. Supports dual frontends (Inertia/React and Blade/Livewire), multi-currency, 10 financial reports, bank reconciliation, and a complete REST API.

---

## Features

- ✅ **Double-entry bookkeeping** with journal entries and posting workflow
- ✅ **Chart of Accounts** with hierarchical tree structure
- ✅ **Multi-currency** support with exchange rates
- ✅ **10 Financial Reports**: General Ledger, Trial Balance, Balance Sheet, Income Statement, Cash Flow, Aged Payables, Aged Receivables, Bank Book, Cash Book, Account Statement
- ✅ **Bank Reconciliation** with automated matching
- ✅ **Dual Frontend**: Inertia/React (default) or Blade/Livewire
- ✅ **Full REST API** with JSON resources
- ✅ **9 Artisan Commands** for install, seed, sync, verify, health check
- ✅ **Spatie Permission** integration for role-based access control
- ✅ **Accounting Periods** with open/close/fiscal year workflow
- ✅ **Cost Centers** and Tax Codes/Rates
- ✅ **Audit Logging** for all accounting transactions

---

## Requirements

- PHP ^8.2
- Laravel ^10.0 | ^11.0 | ^12.0 | ^13.0

---

## Installation

Install via Composer:

```bash
composer require alimarchal/laravel-chart-of-accounts
```

Run the one-command installer (publishes migrations for this package **and** Spatie dependencies, runs them, seeds master data, and verifies):

```bash
php artisan accounting:install
```

> **Blade/Livewire apps** (e.g. Jetstream Livewire): set `ACCOUNTING_UI_DRIVER=blade` in your `.env` before installing.
> **Inertia/React apps**: the default driver (`inertia`) works out of the box.

**What `accounting:install` does automatically:**

1. Publishes accounting migrations (`--tag=accounting-migrations`)
2. Publishes `spatie/laravel-permission` migrations (if not already present)
3. Publishes `spatie/laravel-activitylog` migrations (if not already present)
4. Runs `php artisan migrate`
5. Seeds all master data (account types, currencies, COA, permissions, tax codes, etc.)
6. Syncs database objects (functions, procedures)
7. Verifies the installation

**Manual step-by-step** (if you prefer):

```bash
php artisan vendor:publish --tag=accounting-migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
php artisan accounting:seed
```

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=accounting-config
```

Key options in `config/accounting.php`:

```php
return [
    // 'inertia' (default) or 'blade'
    'ui_driver' => env('ACCOUNTING_UI_DRIVER', 'inertia'),

    // Route prefix
    'route_prefix' => 'accounting',

    // Middleware applied to all accounting routes
    'middleware' => ['web', 'auth'],

    // Default currency
    'default_currency' => 'USD',

    // Enable/disable permission checks (requires spatie/laravel-permission)
    'use_permissions' => true,
];
```

---

## Frontend Stack Selection

### Inertia/React (default)

Install the required packages:

```bash
composer require inertiajs/inertia-laravel
npm install @inertiajs/react react react-dom
```

Publish JS pages:

```bash
php artisan vendor:publish --tag=accounting-js
```

### Blade/Livewire

Set in your `.env`:

```
ACCOUNTING_UI_DRIVER=blade
```

Install Livewire:

```bash
composer require livewire/livewire
```

Publish views:

```bash
php artisan vendor:publish --tag=accounting-views
```

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `accounting:install` | Run migrations, seed, sync DB objects, and verify |
| `accounting:seed` | Seed account types, currencies, chart of accounts, periods |
| `accounting:sync-db-objects` | Sync database views, triggers, and stored procedures |
| `accounting:verify` | Verify accounting data integrity |
| `accounting:health-check` | Run accounting health checks |
| `accounting:rebuild-snapshots` | Rebuild account balance snapshots |
| `accounting:close-fiscal-year` | Close the current fiscal year |
| `accounting:close-period` | Close the current accounting period |
| `accounting:open-period` | Open a new accounting period |

---

## REST API

All API endpoints are prefixed with `/api/accounting/v1`.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/account-types` | List account types |
| POST | `/account-types` | Create account type |
| GET | `/account-types/{id}` | Show account type |
| PUT | `/account-types/{id}` | Update account type |
| DELETE | `/account-types/{id}` | Delete account type |
| GET | `/chart-of-accounts` | List chart of accounts |
| POST | `/chart-of-accounts` | Create account |
| GET | `/currencies` | List currencies |
| GET | `/journal-entries` | List journal entries |
| POST | `/journal-entries` | Create journal entry |
| POST | `/journal-entries/{id}/post` | Post a draft entry |
| POST | `/journal-entries/{id}/void` | Void a posted entry |
| POST | `/journal-entries/{id}/reverse` | Reverse a posted entry |
| GET | `/reconciliations` | List reconciliations |
| POST | `/reconciliations` | Create reconciliation |
| GET | `/bank-accounts` | List bank accounts |
| GET | `/cost-centers` | List cost centers |
| GET | `/tax-codes` | List tax codes |
| GET | `/tax-rates` | List tax rates |
| GET | `/accounting-periods` | List periods |
| GET | `/account-balance-snapshots` | List balance snapshots |

---

## Models

| Model | Table | Description |
|-------|-------|-------------|
| `AccountType` | `accounting_account_types` | Account classification |
| `ChartOfAccount` | `accounting_chart_of_accounts` | Account tree |
| `Currency` | `accounting_currencies` | Currencies & exchange rates |
| `AccountingPeriod` | `accounting_periods` | Fiscal periods |
| `JournalEntry` | `accounting_journal_entries` | Journal entry header |
| `JournalEntryLine` | `accounting_journal_entry_lines` | Debit/credit lines |
| `BankAccount` | `accounting_bank_accounts` | Bank accounts |
| `Reconciliation` | `accounting_reconciliations` | Bank reconciliations |
| `TaxCode` | `accounting_tax_codes` | Tax codes |
| `TaxRate` | `accounting_tax_rates` | Tax rates |
| `AccountingAuditLog` | `accounting_audit_logs` | Audit trail |
| `AccountBalanceSnapshot` | `accounting_account_balance_snapshots` | Period snapshots |
| `CostCenter` | `accounting_cost_centers` | Cost center allocation |

---

## Permissions

When `use_permissions` is enabled, the package registers these Spatie permissions:

- `accounting.view`
- `accounting.create`
- `accounting.edit`
- `accounting.delete`
- `accounting.post`
- `accounting.void`
- `accounting.reverse`
- `accounting.close-period`
- `accounting.reports`

Assign them to roles via `spatie/laravel-permission`.

---

## Testing

```bash
composer test
```

Or with PHPUnit:

```bash
vendor/bin/pest
```

---

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
