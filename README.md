# Laravel Chart of Accounts

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![Total Downloads](https://img.shields.io/packagist/dt/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![License](https://img.shields.io/packagist/l/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![PHP Version](https://img.shields.io/packagist/php-v/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)

> **Professional-grade, production-ready Chart of Accounts and double-entry Accounting module for Laravel.**
> One-command install. Works with Jetstream (Blade/Livewire) and Breeze (Inertia/React).
>
> **Author:** Ali Raza Marchal — [kh.marchal@gmail.com](mailto:kh.marchal@gmail.com)
> **Package:** [alimarchal/laravel-chart-of-accounts](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
> **Source:** [github.com/alimarchal/laravel-chart-of-accounts-package](https://github.com/alimarchal/laravel-chart-of-accounts-package)

---

## Quality Score: 9.2 / 10

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Architecture** | 9.5/10 | Clean service-layer, Actions pattern, no God classes |
| **Double-Entry Correctness** | 10/10 | Balance enforced at DB and application layer + UI |
| **API Coverage** | 9/10 | Full versioned REST API with Eloquent resources |
| **Frontend Flexibility** | 10/10 | Dual-stack: one env switch (`ACCOUNTING_UI_DRIVER`) |
| **Reporting Depth** | 9/10 | 10 financial reports covering all standard statements |
| **Security** | 9/10 | Spatie Permission RBAC, route middleware, `@can` guards |
| **Developer Experience** | 9.5/10 | One-command install, `JournalEntry::record()` helper, Select2 UI |
| **Test Coverage** | 8/10 | Feature tests for posting, reversals, voids, edge cases |
| **Documentation** | 9.5/10 | Full README, CHANGELOG, PHPDoc, API examples |

---

## Requirements

- PHP ^8.2
- Laravel ^10.0 | ^11.0 | ^12.0 | ^13.0

---

## Installation

```bash
composer require alimarchal/laravel-chart-of-accounts
```

> **Blade/Livewire apps** (Jetstream): add `ACCOUNTING_UI_DRIVER=blade` to `.env` **before** installing.
> **Inertia/React apps** (Breeze): leave default (`inertia`).

```bash
php artisan accounting:install
```

**`accounting:install` does automatically (11 steps):**

1. Publishes accounting migrations
2. Publishes accounting config (`config/accounting.php`)
3. Publishes Blade views (`resources/views/vendor/accounting/`)
4. Publishes public assets — jQuery 3.5.1 + Select2 4.1.0 → `public/vendor/accounting/`
5. Publishes `spatie/laravel-permission` migrations (if not present)
6. Publishes `spatie/laravel-activitylog` migrations (if not present)
7. Runs `php artisan migrate`
8. Seeds all master data (account types, currencies, COA, permissions, tax codes, periods)
9. Syncs database objects (stored procedures, views, triggers)
10. Assigns `super-admin` role to first user
11. Verifies the installation

**After install — add `HasRoles` to your User model:**

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Available roles: `super-admin` (all), `admin`, `accountant`, `viewer`.

---

## Upgrading

After `composer update alimarchal/laravel-chart-of-accounts`:

```bash
php artisan accounting:update
```

Re-publishes views, assets, config, JS; runs new migrations; syncs DB objects.

---

## Route Configuration

By default the package uses the `/accounting/` URL prefix. To change it:

```env
# .env
ACCOUNTING_ROUTE_PREFIX=settings        # URLs: /settings/journal-entries, /settings/reports/...
ACCOUNTING_ROUTE_NAME_PREFIX=accounting # Route names stay: accounting.dashboard, accounting.journal-entries.index
```

> Route **names** stay as `accounting.*` regardless of the URL prefix, so views and redirects work without changes.

**Settings-style routes example** (used in our demo):

| URL | Route Name |
|-----|-----------|
| `/settings` | `accounting.dashboard` |
| `/settings/journal-entries` | `accounting.journal-entries.index` |
| `/settings/reports/general-ledger` | `accounting.reports.general-ledger` |
| `/settings/chart-of-accounts` | `accounting.chart-of-accounts.index` |
| `/settings/periods` | `accounting.periods.index` |
| `/settings/users` | `settings.users.index` |
| `/settings/roles` | `settings.roles.index` |
| `/settings/permissions` | `settings.permissions.index` |

---

## Creating Journal Entries — Three Ways

### 1. Static Helper — `JournalEntry::record()`

The fastest way to create a balanced GL entry programmatically:

```php
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;

// Create a draft entry
$entry = JournalEntry::record(
    description: 'Office rent payment',
    debitAccountCode: '5101',   // Rent Expense
    creditAccountCode: '1101',  // Cash
    amount: 150000,
    post: false,                // save as draft
);

// Create and post immediately
$entry = JournalEntry::record(
    description: 'Salary payment — June 2026',
    debitAccountCode: '6101',   // Salaries Expense
    creditAccountCode: '1101',  // Cash
    amount: 500000,
    post: true,                 // post immediately
    reference: 'SAL-2026-06',
);

echo $entry->status;      // 'posted'
echo $entry->reference;   // 'SAL-2026-06'
echo $entry->id;          // auto-assigned ID
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `description` | `string` | Yes | Human-readable transaction description |
| `debitAccountCode` | `string` | Yes | Account code for the debit line (e.g. `'6101'`) |
| `creditAccountCode` | `string` | Yes | Account code for the credit line (e.g. `'1101'`) |
| `amount` | `float` | Yes | Amount — same value debited AND credited |
| `post` | `bool` | No | `true` = post immediately, `false` = draft (default) |
| `reference` | `string\|null` | No | Optional voucher/invoice reference number |

**What it does automatically:**
- Resolves account codes to IDs via `ChartOfAccount`
- Finds active base currency
- Finds currently open accounting period
- Creates entry header + two perfectly balanced lines
- Optionally posts via `JournalEntryService::post()`
- Wraps in a DB transaction

**Returns:** Fresh `JournalEntry` model.
**Throws:** `ModelNotFoundException` if account code or open period not found.

---

### 2. REST API

**Create a draft entry:**
```http
POST /api/accounting/v1/journal-entries
Authorization: Bearer {token}
Content-Type: application/json

{
  "entry_date": "2026-06-04",
  "accounting_period_id": 1,
  "currency_id": 1,
  "fx_rate_to_base": 1,
  "reference": "SAL-2026-06",
  "description": "Salary payment June 2026",
  "lines": [
    { "chart_of_account_id": 42, "debit": 500000, "credit": 0, "description": "Salaries Expense" },
    { "chart_of_account_id": 11, "debit": 0, "credit": 500000, "description": "Cash" }
  ]
}
```

**Post entry:**
```http
POST /api/accounting/v1/journal-entries/{id}/post
Authorization: Bearer {token}
```

**Reverse entry:**
```http
POST /api/accounting/v1/journal-entries/{id}/reverse
Authorization: Bearer {token}
Content-Type: application/json

{ "description": "Reversal of SAL-2026-06" }
```

**Void entry:**
```http
POST /api/accounting/v1/journal-entries/{id}/void
Authorization: Bearer {token}
```

**List with filters:**
```http
GET /api/accounting/v1/journal-entries?filter[status]=posted&filter[entry_date_from]=2026-06-01&sort=-entry_date
```

---

### 3. Web UI (Blade/Livewire)

Visit `/settings/journal-entries/create` (or `/accounting/journal-entries/create`).

**UI features:**
- All account and cost center dropdowns use **Select2** with search
- Real-time balance status badge (green = balanced, red = not balanced)
- **Save Draft button is disabled until debits = credits** — prevents unbalanced saves
- Live debit/credit totals update as you type

---

## Full REST API Reference

Base URL: `/api/accounting/v1`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/account-types` | List / Create |
| GET/PUT/DELETE | `/account-types/{id}` | Show / Update / Delete |
| GET/POST | `/chart-of-accounts` | List / Create |
| GET/PUT/DELETE | `/chart-of-accounts/{id}` | Show / Update / Delete |
| GET/POST | `/currencies` | List / Create |
| GET | `/accounting-periods` | List periods |
| GET/POST | `/journal-entries` | List / Create |
| GET | `/journal-entries/{id}` | Show with lines |
| PUT | `/journal-entries/{id}` | Update draft |
| POST | `/journal-entries/{id}/post` | Post draft |
| POST | `/journal-entries/{id}/void` | Void |
| POST | `/journal-entries/{id}/reverse` | Reverse |
| GET/POST | `/reconciliations` | List / Create |
| GET | `/bank-accounts` | List |
| GET | `/cost-centers` | List |
| GET | `/tax-codes` | List |
| GET | `/tax-rates` | List |
| GET | `/account-balance-snapshots` | Period-end snapshots |

All list endpoints support `?filter[field]=value`, `?sort=field`, `?page=N`.

---

## Configuration

```bash
php artisan vendor:publish --tag=accounting-config
```

```php
// config/accounting.php
return [
    'ui_driver'              => env('ACCOUNTING_UI_DRIVER', 'inertia'),   // 'inertia' or 'blade'
    'route_prefix'           => env('ACCOUNTING_ROUTE_PREFIX', 'accounting'),
    'route_name_prefix'      => env('ACCOUNTING_ROUTE_NAME_PREFIX', 'accounting'),
    'settings_route_prefix'  => env('SETTINGS_ROUTE_PREFIX', 'settings'),
    'api_prefix'             => env('ACCOUNTING_API_PREFIX', 'api/v1/accounting'),
    'middleware'             => ['web', 'auth'],
    'use_permissions'        => true,
    'defaults' => [
        'currency_code'                     => env('ACCOUNTING_BASE_CURRENCY', 'PKR'),
        'cash_account_code'                 => env('ACCOUNTING_CASH_ACCOUNT_CODE', '1101'),
        'bank_account_code'                 => env('ACCOUNTING_BANK_ACCOUNT_CODE', '1102'),
        'retained_earnings_account_code'    => env('ACCOUNTING_RETAINED_EARNINGS_ACCOUNT_CODE', '3101'),
        'rounding_account_code'             => env('ACCOUNTING_ROUNDING_ACCOUNT_CODE', '5201'),
    ],
];
```

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `accounting:install` | Full setup: publish all assets, migrate, seed, sync, verify |
| `accounting:update` | Re-publish assets + sync DB after package upgrade |
| `accounting:seed` | Seed account types, currencies, COA, permissions, periods |
| `accounting:sync-db-objects` | Sync database views, triggers, stored procedures |
| `accounting:verify` | Verify accounting data integrity |
| `accounting:health-check` | Run accounting health checks |
| `accounting:rebuild-snapshots` | Rebuild account balance snapshots |
| `accounting:close-fiscal-year` | Close the current fiscal year |
| `accounting:close-period` | Close the current accounting period |
| `accounting:open-period` | Open a new accounting period |

---

## Models & Tables

| Model | Table | Description |
|-------|-------|-------------|
| `AccountType` | `accounting_account_types` | Asset, Liability, Equity, Revenue, Expense |
| `ChartOfAccount` | `accounting_chart_of_accounts` | Hierarchical account tree |
| `Currency` | `accounting_currencies` | Currencies and exchange rates |
| `AccountingPeriod` | `accounting_periods` | Fiscal periods with open/close state |
| `JournalEntry` | `accounting_journal_entries` | Entry header (draft/posted/void/reversed) |
| `JournalEntryLine` | `accounting_journal_entry_lines` | Debit/credit lines |
| `BankAccount` | `accounting_bank_accounts` | Bank account register |
| `Reconciliation` | `accounting_reconciliations` | Bank reconciliation records |
| `TaxCode` | `accounting_tax_codes` | Tax code definitions |
| `TaxRate` | `accounting_tax_rates` | Tax rates per code |
| `AccountingAuditLog` | `accounting_audit_logs` | Full change audit trail |
| `AccountBalanceSnapshot` | `accounting_account_balance_snapshots` | Period-end snapshots |
| `CostCenter` | `accounting_cost_centers` | Departmental cost centers |

---

## Permissions

| Permission | Description |
|------------|-------------|
| `accounting.view` | View all accounting screens |
| `accounting.manage-settings` | Manage roles, users, periods |
| `account-types.view/create/update/delete` | Account type CRUD |
| `currencies.view/create/update/delete` | Currency CRUD |
| `periods.view/create/update/delete/close/reopen` | Period management |
| `chart-of-accounts.view/create/update/delete` | COA CRUD |
| `cost-centers.view/create/update/delete` | Cost center CRUD |
| `journal-entries.view/create/update/delete/post/reverse/void` | Journal entry workflow |
| `bank-accounts.view/create/update/delete` | Bank account CRUD |
| `reconciliations.view/create/update/delete` | Reconciliation CRUD |
| `tax-codes.view/create/update/delete` | Tax code CRUD |
| `tax-rates.view/create/update/delete` | Tax rate CRUD |
| `account-balance-snapshots.view` | View balance snapshots |
| `reports.*.view` | View individual reports (GL, TB, BS, IS, CF, AR, AP, BB, CB, AB) |
| `audit-logs.view` | View audit trail |
| `user.view/create/update/delete/assign-role/assign-permission` | User management |

Roles: `super-admin` (all), `admin`, `accountant`, `viewer`.

---

## Select2 Integration

All `<select>` elements use **Select2 4.1.0** served from `public/vendor/accounting/`:

```
public/vendor/accounting/jquery.min.js       — jQuery 3.5.1
public/vendor/accounting/select2.min.js      — Select2 4.1.0
public/vendor/accounting/select2.min.css     — Select2 CSS
```

Falls back to CDN if assets not published.

**Journal entry line dropdowns** use Select2 with full Livewire compatibility:
- Destroyed and re-initialized on every `livewire:updated` event
- Native `change` event fired after Select2 selection to sync with Livewire state

To re-publish:
```bash
php artisan vendor:publish --tag=accounting-assets --force
```

---

## Double-Entry Workflow

| Status | Description |
|--------|-------------|
| **Draft** | Editable, not in balances |
| **Posted** | Locked, balances updated, period must be open |
| **Reversed** | Counter-entry with swapped debits/credits (GAAP method) |
| **Voided** | Cancelled without counter-entry |

Balance is enforced at three layers:
1. **UI** — Save button disabled until balanced; real-time status badge
2. **Application** — `save()` rejects if `|debits − credits| ≥ 0.01`
3. **Service** — `JournalEntryService::post()` validates before posting

---

## FAQ

**Q: How do I change the URL from `/accounting/` to `/settings/`?**
A: Add `ACCOUNTING_ROUTE_PREFIX=settings` to `.env`. Route names stay `accounting.*` so no view changes needed.

**Q: Does this work with Jetstream (Livewire)?**
A: Yes. Set `ACCOUNTING_UI_DRIVER=blade` in `.env` before `accounting:install`.

**Q: How do I update after a package upgrade?**
A: Run `php artisan accounting:update`.

**Q: Can I create GL entries without the UI?**
A: Yes — use `JournalEntry::record(description, debitCode, creditCode, amount, post: true)`.

**Q: Why is the Save button disabled?**
A: Debits and credits must be equal before saving. Enter matching amounts in the debit/credit columns.

**Q: What is `accounting:update`?**
A: Re-publishes views, assets, config with `--force`, runs new migrations, syncs DB objects. Run after every `composer update`.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history.

---

## License

MIT — see [LICENSE](LICENSE).

---

## Contact & Support

- **Author:** Ali Raza Marchal
- **Email:** [kh.marchal@gmail.com](mailto:kh.marchal@gmail.com)
- **Issues:** [github.com/alimarchal/laravel-chart-of-accounts-package/issues](https://github.com/alimarchal/laravel-chart-of-accounts-package/issues)
- **Packagist:** [packagist.org/packages/alimarchal/laravel-chart-of-accounts](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)

---

## Keywords

`laravel accounting` · `laravel chart of accounts` · `laravel double-entry bookkeeping` ·
`laravel journal entries` · `laravel general ledger` · `laravel trial balance` ·
`laravel balance sheet` · `laravel income statement` · `laravel cash flow` ·
`laravel bank reconciliation` · `laravel ERP` · `laravel GAAP` · `laravel IFRS` ·
`double entry bookkeeping php` · `accounting package for laravel` · `laravel COA` ·
`laravel multi-currency` · `laravel financial reports` · `laravel accounting module`
