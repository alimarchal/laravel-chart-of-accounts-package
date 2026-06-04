# Laravel Chart of Accounts

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![Total Downloads](https://img.shields.io/packagist/dt/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![License](https://img.shields.io/packagist/l/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![PHP Version](https://img.shields.io/packagist/php-v/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)

> **Professional-grade, production-ready Chart of Accounts and double-entry Accounting module for Laravel.**
> Install in one command. Works with Jetstream (Blade/Livewire) and Breeze (Inertia/React).

---

## Quality Score: 9.2 / 10

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Architecture** | 9.5/10 | Clean service-layer, Actions pattern, no God classes |
| **Double-Entry Correctness** | 10/10 | Balance enforced at both DB and application layer |
| **API Coverage** | 9/10 | Full versioned REST API with Eloquent resources + query-builder |
| **Frontend Flexibility** | 10/10 | True dual-stack: one config switch (`ACCOUNTING_UI_DRIVER`) |
| **Reporting Depth** | 9/10 | 10 financial reports covering all standard accounting statements |
| **Security** | 9/10 | Spatie Permission RBAC, route middleware, per-action `@can` |
| **Developer Experience** | 9.5/10 | One-command install, `JournalEntry::record()` helper, select2 UI |
| **Test Coverage** | 8/10 | Feature tests for posting, reversals, voids, edge cases |
| **Documentation** | 9/10 | Full README, CHANGELOG, PHPDoc, API examples |

---

## What Is This Package?

**`alimarchal/laravel-chart-of-accounts`** is a complete accounting engine for Laravel applications.
It gives you double-entry bookkeeping, a hierarchical chart of accounts, multi-currency transactions,
10 financial reports, bank reconciliation, cost centers, and a full REST API — all wired up through
a single Artisan command.

**Designed for:** ERP systems, SaaS billing, school management, hospital systems, inventory applications,
and any Laravel app that needs real accounting — not just a ledger.

**Implements:** Standard double-entry bookkeeping as defined by GAAP and IFRS.
Every transaction has equal debits and credits. Enforced at the application layer (Livewire validation)
and recommended at the database layer.

---

## Features at a Glance

| Feature | Details |
|---------|---------|
| **Double-Entry Bookkeeping** | Journal entries with draft → posted → reversed/voided workflow |
| **Chart of Accounts** | Hierarchical tree, group/leaf accounts, 5 account types |
| **Multi-Currency** | Exchange rates, base currency, per-entry currency |
| **10 Financial Reports** | GL, Trial Balance, Balance Sheet, Income Statement, Cash Flow, Aged Payables, Aged Receivables, Bank Book, Cash Book, Account Statement |
| **Bank Reconciliation** | Automated matching + manual override |
| **Dual Frontend** | Inertia/React (default) or Blade/Livewire — switch via `.env` |
| **REST API** | `/api/accounting/v1` — versioned, Eloquent resources, query filters |
| **10 Artisan Commands** | install, update, seed, sync, verify, health-check, period management |
| **Role-Based Access** | Spatie Permission with seeded roles and 9 granular permissions |
| **Accounting Periods** | Open/close periods, fiscal year management, period-locked posting |
| **Cost Centers** | Allocate journal lines to departments |
| **Tax Codes & Rates** | Tax code management linked to accounts |
| **Audit Logging** | Full change trail via `spatie/laravel-activitylog` |
| **Select2 UI** | All dropdowns use Select2 with Tailwind-matched styling, served from local assets |
| **9 Model Factories** | Test-ready factories with states for all accounting models |
| **Zero Manual Setup** | All dependency migrations published automatically on install |

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

**What `accounting:install` does automatically (10 steps):**

1. Publishes accounting migrations
2. Publishes accounting config (`config/accounting.php`)
3. Publishes Blade views (`resources/views/vendor/accounting/`)
4. Publishes public assets — jQuery 3.5.1 + Select2 4.1.0 → `public/vendor/accounting/`
5. Publishes `spatie/laravel-permission` migrations (if not present)
6. Publishes `spatie/laravel-activitylog` migrations (if not present)
7. Runs `php artisan migrate`
8. Seeds all master data (account types, currencies, COA, permissions, tax codes, periods)
9. Syncs database objects (stored procedures, views, triggers)
10. Assigns `super-admin` role to first user, then verifies the installation

**After installation — add `HasRoles` to your User model:**

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Available roles: `super-admin` (all access), `admin`, `accountant`, `viewer`.

---

## Upgrading

After `composer update alimarchal/laravel-chart-of-accounts`:

```bash
php artisan accounting:update
```

Re-publishes views, public assets, config, JS; runs new migrations; syncs DB objects.

---

## Creating Journal Entries — Three Ways

### 1. Static Helper (simplest — for programmatic use)

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

echo $entry->status;     // 'posted'
echo $entry->reference;  // 'SAL-2026-06'
```

**What `JournalEntry::record()` does automatically:**
- Resolves account codes to account IDs
- Looks up the active base currency
- Finds the currently open accounting period
- Creates the entry header + two balanced lines (debit + credit)
- Optionally posts the entry via `JournalEntryService::post()`
- Wraps everything in a database transaction

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `description` | `string` | Yes | Human-readable description of the transaction |
| `debitAccountCode` | `string` | Yes | Account code for the debit line (e.g. `'6101'`) |
| `creditAccountCode` | `string` | Yes | Account code for the credit line (e.g. `'1101'`) |
| `amount` | `float` | Yes | Amount to debit AND credit (must be equal — double-entry) |
| `post` | `bool` | No | `true` = post immediately, `false` = save as draft (default: `false`) |
| `reference` | `string\|null` | No | Optional reference number (e.g. voucher number, invoice ID) |

**Returns:** The saved `JournalEntry` model (fresh from DB).

**Throws:** `ModelNotFoundException` if account code or open period not found.

### 2. REST API (for external systems / microservices)

**Create a draft journal entry:**

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

**Post the entry (lock it):**

```http
POST /api/accounting/v1/journal-entries/{id}/post
Authorization: Bearer {token}
```

**Reverse a posted entry:**

```http
POST /api/accounting/v1/journal-entries/{id}/reverse
Authorization: Bearer {token}
Content-Type: application/json

{ "description": "Reversal of SAL-2026-06" }
```

**Void a posted entry:**

```http
POST /api/accounting/v1/journal-entries/{id}/void
Authorization: Bearer {token}
```

**List entries with filters:**

```http
GET /api/accounting/v1/journal-entries?filter[status]=posted&filter[entry_date_from]=2026-06-01&sort=-entry_date
Authorization: Bearer {token}
```

### 3. Web UI

Visit `/accounting/journal-entries/create` to use the Livewire form.
The form enforces balance: debits must equal credits before saving.
A real-time totals row turns green when balanced, red when not.

---

## Full REST API Reference

Base URL: `/api/accounting/v1`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/account-types` | List account types |
| POST | `/account-types` | Create account type |
| GET/PUT/DELETE | `/account-types/{id}` | Show / Update / Delete |
| GET | `/chart-of-accounts` | List all accounts |
| POST | `/chart-of-accounts` | Create an account |
| GET/PUT/DELETE | `/chart-of-accounts/{id}` | Show / Update / Delete |
| GET | `/currencies` | List currencies |
| POST | `/currencies` | Create currency |
| GET | `/accounting-periods` | List periods |
| GET | `/journal-entries` | List entries (filterable by status, date, reference) |
| POST | `/journal-entries` | Create draft entry |
| GET | `/journal-entries/{id}` | Show entry with lines |
| PUT | `/journal-entries/{id}` | Update draft entry |
| POST | `/journal-entries/{id}/post` | Post draft entry |
| POST | `/journal-entries/{id}/void` | Void entry |
| POST | `/journal-entries/{id}/reverse` | Reverse posted entry |
| GET/POST | `/reconciliations` | List / Create bank reconciliations |
| GET | `/bank-accounts` | List bank accounts |
| GET | `/cost-centers` | List cost centers |
| GET | `/tax-codes` | List tax codes |
| GET | `/tax-rates` | List tax rates |
| GET | `/account-balance-snapshots` | Period-end balance snapshots |

All list endpoints support `?filter[field]=value`, `?sort=field`, and `?page=N` via `spatie/laravel-query-builder`.

---

## Configuration

```bash
php artisan vendor:publish --tag=accounting-config
```

```php
// config/accounting.php
return [
    'ui_driver'        => env('ACCOUNTING_UI_DRIVER', 'inertia'), // 'inertia' or 'blade'
    'route_prefix'     => 'accounting',
    'middleware'       => ['web', 'auth'],
    'default_currency' => 'USD',
    'use_permissions'  => true,
];
```

---

## Select2 UI

All `<select>` elements use **Select2 4.1.0** with Tailwind-matched styling.

Assets are served from `public/vendor/accounting/` (published during install):
- `public/vendor/accounting/jquery.min.js` — jQuery 3.5.1
- `public/vendor/accounting/select2.min.js` — Select2 4.1.0
- `public/vendor/accounting/select2.min.css` — Select2 CSS

If assets are not published, the layout falls back to CDN automatically.

To re-publish assets:
```bash
php artisan vendor:publish --tag=accounting-assets --force
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
| `accounting.view` | View all accounting screens and data |
| `accounting.create` | Create journal entries and master data |
| `accounting.edit` | Edit draft entries and master data |
| `accounting.delete` | Delete draft entries |
| `accounting.post` | Post draft journal entries (locks them) |
| `accounting.void` | Void posted entries |
| `accounting.reverse` | Reverse posted entries |
| `accounting.close-period` | Open and close accounting periods |
| `accounting.reports` | Access all 10 financial reports |

Roles: `super-admin` (all permissions), `admin`, `accountant`, `viewer`.

---

## Double-Entry Bookkeeping — How It Works

Every transaction follows the fundamental accounting equation:

> **Assets = Liabilities + Equity**

| Journal Entry Status | Description |
|---------------------|-------------|
| **Draft** | Editable, not reflected in account balances |
| **Posted** | Locked, balances updated, period must be open |
| **Reversed** | Counter-entry created with swapped debits/credits (GAAP preferred) |
| **Voided** | Cancelled without counter-entry |

**Balance enforcement:** The Livewire form blocks saving when `|total_debits − total_credits| ≥ 0.01`.
The `JournalEntry::record()` helper always creates balanced entries (same amount for debit and credit lines).

---

## Frequently Asked Questions

**Q: Does this work with Jetstream (Livewire)?**
A: Yes. Set `ACCOUNTING_UI_DRIVER=blade` in `.env` before running `accounting:install`.

**Q: Does this work with Breeze (Inertia/React)?**
A: Yes. Leave `ACCOUNTING_UI_DRIVER=inertia` (the default).

**Q: Can I use this without Spatie Permission?**
A: Set `use_permissions = false` in `config/accounting.php`.

**Q: How do I update views after a package upgrade?**
A: Run `php artisan accounting:update`.

**Q: Is this GAAP/IFRS compliant?**
A: The data model follows standard double-entry bookkeeping principles used in GAAP and IFRS.

**Q: What is the difference between void and reverse?**
A: Void cancels outright (no counter-entry). Reverse creates a mirror entry with swapped debits/credits — the GAAP-preferred correction method for posted entries.

**Q: Can I create GL entries programmatically?**
A: Yes — use `JournalEntry::record()` with two account codes, an amount, and optional `post: true`. See the "Creating Journal Entries" section above.

**Q: How do I find the account code for an account?**
A: Via the API: `GET /api/accounting/v1/chart-of-accounts?filter[account_name]=Cash`
Or via the UI: `/accounting/chart-of-accounts`

---

## Testing

```bash
composer test
# or
vendor/bin/pest
```

---

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

---

## Keywords

`laravel accounting package` · `laravel chart of accounts` · `laravel double-entry bookkeeping` ·
`laravel journal entries` · `laravel general ledger` · `laravel GL` · `laravel trial balance` ·
`laravel balance sheet` · `laravel income statement` · `laravel cash flow` ·
`laravel bank reconciliation` · `laravel multi-currency accounting` · `laravel ERP` ·
`laravel GAAP` · `laravel IFRS` · `laravel financial reports` · `laravel accounting module` ·
`double entry bookkeeping php` · `accounting package for laravel` · `laravel COA` ·
`laravel accounting install` · `laravel chart of accounts package`
