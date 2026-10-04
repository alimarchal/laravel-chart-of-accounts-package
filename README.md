# Laravel Chart of Accounts

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![Total Downloads](https://img.shields.io/packagist/dt/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![License](https://img.shields.io/packagist/l/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
[![PHP Version](https://img.shields.io/packagist/php-v/alimarchal/laravel-chart-of-accounts.svg?style=flat-square)](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)

> **Chart of Accounts and double-entry accounting module for Laravel.**
> One-command install. Works with Jetstream (Blade/Livewire) and Breeze (Inertia/React).
>
> **Author:** Ali Raza Marchal — [kh.marchal@gmail.com](mailto:kh.marchal@gmail.com)
> **Package:** [alimarchal/laravel-chart-of-accounts](https://packagist.org/packages/alimarchal/laravel-chart-of-accounts)
> **Source:** [github.com/alimarchal/laravel-chart-of-accounts-package](https://github.com/alimarchal/laravel-chart-of-accounts-package)

---

## Highlights

- Double-entry journal with draft → posted → reversed / void workflow
- Balance enforced in **exact cents**; posted entries are **immutable at the database layer** (triggers on MySQL/MariaDB, PostgreSQL, SQLite)
- Hierarchical chart of accounts with integrity rules (no cycles, same-type parents, structural fields locked once used)
- Accounting periods with close / reopen / fiscal-year close, balance snapshots, retained-earnings roll-forward
- 10+ reports (GL, trial balance, balance sheet, income statement, cash flow, aged AR/AP, bank & cash book, …)
- REST API (versioned), Inertia/React and Blade/Livewire UIs
- Spatie Permission RBAC with privilege-escalation protection, full audit trail
- Test suite (Pest + Testbench) run in CI on PHP 8.2–8.4, Laravel 11–13, SQLite/MySQL/MariaDB/PostgreSQL; Larastan level 5

---

## How it works (visual guide)

> Every diagram is generated from the Mermaid sources in [`docs/diagrams/`](docs/diagrams) (SVG + PNG in [`docs/images/`](docs/images)).

### 1. Architecture — who calls what

![Architecture](docs/images/01-architecture.png)

### 2. Installation flow

![Installation flow](docs/images/02-installation.png)

### 3. Journal entry lifecycle

![Journal entry lifecycle](docs/images/03-journal-lifecycle.png)

### 4. What happens when an entry is posted

![Posting checks](docs/images/04-posting-checks.png)

### 5. Month-end and year-end close

![Period close](docs/images/05-period-close.png)

### 6. An API request, step by step

![API request](docs/images/06-api-request.png)

---

## Requirements

- PHP ^8.2 (Laravel 13 requires PHP ^8.3)
- Laravel ^11.0 | ^12.0 | ^13.0
- MySQL 8 / MariaDB 10.6+ / PostgreSQL 13+ / SQLite 3.35+
- `livewire/livewire` ^3|^4 for the Blade UI, or `inertiajs/inertia-laravel` for the React UI
- `laravel/sanctum` (or another guard configured in `ACCOUNTING_API_MIDDLEWARE`) for the API

---

## Installation

Tested end-to-end on a fresh **Laravel 13** app: install takes about one second and the first API call works immediately.

```bash
# 1. Package
composer require alimarchal/laravel-chart-of-accounts

# 2. Choose a UI in .env (default: inertia)
#    ACCOUNTING_UI_DRIVER=inertia   React (Breeze / React starter kit)
#    ACCOUNTING_UI_DRIVER=blade     Blade + Livewire (composer require livewire/livewire)
#    ACCOUNTING_UI_DRIVER=api       REST API only — no web routes, views or Livewire (lightest)

# 3. API authentication (Laravel 11+ ships without it)
php artisan install:api
```

```php
// 4. app/Models/User.php
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;
}
```

```bash
# 5. Install (idempotent — safe to re-run)
php artisan accounting:install --admin-email=you@example.com
```

> Without `--admin-email` the `super-admin` role goes to the **first user** in the users table — verify that is intended.

**`accounting:install` does automatically:**

1. Publishes the config (`config/accounting.php`) and migrations
2. Publishes `spatie/laravel-permission` and `spatie/laravel-activitylog` migrations (if missing)
3. Publishes the React pages (`inertia`) or the Select2/jQuery assets (`blade`); `--views` also publishes Blade views for customisation
4. Runs `migrate --force` (works in production)
5. Seeds account types, currencies, chart of accounts, current period, roles/permissions and tax codes (never overwrites existing data)
6. Syncs database objects: reporting views, audit triggers and posted-entry immutability triggers
7. Assigns `super-admin` (to `--admin-email`, or the first user)
8. Verifies the installation and warns if the Sanctum guard is missing

Available roles: `super-admin` (all), `admin`, `accountant`, `viewer`.

---

## API quick start

```bash
# A token for an existing user (or issue tokens from your own login endpoint)
php artisan tinker --execute="echo App\Models\User::first()->createToken('erp')->plainTextToken;"
```

```bash
TOKEN=1|xxxxxxxx
API=http://localhost:8000/api/v1/accounting
H=(-H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json")

# Is everything installed?
curl "${H[@]}" $API/health

# Easiest possible entry: debit one account, credit another (posts immediately)
curl "${H[@]}" -X POST $API/journal-entries/simple \
  -H "Idempotency-Key: rent-2026-10" \
  -d '{"debit_account_code":"5102","credit_account_code":"1101","amount":2500,"description":"Office rent"}'

# Multi-line entry using account codes — no ids needed
curl "${H[@]}" -X POST $API/journal-entries -d '{
  "entry_date": "2026-10-04", "reference": "INV-1001", "auto_post": true,
  "lines": [
    {"account_code": "1103", "debit": 1500, "credit": 0, "cost_center_code": "ADMIN"},
    {"account_code": "4101", "debit": 0, "credit": 1500}
  ]}'

# Balances and reports
curl "${H[@]}" "$API/chart-of-accounts/tree"
curl "${H[@]}" "$API/reports/trial-balance"
curl "${H[@]}" "$API/reports/income-statement?date_from=2026-01-01&date_to=2026-12-31"
curl "${H[@]}" "$API/reports/balance-sheet?as_of_date=2026-12-31"
```

* **Full reference:** [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.1 — open it in Swagger UI, Redoc, Stoplight, or import into Insomnia).
* **Postman:** import [`docs/postman_collection.json`](docs/postman_collection.json), set the `host` and `token` variables, send.
* **Retries are safe:** send an `Idempotency-Key` header when creating entries — a retry returns the original entry (`200`, `Idempotent-Replayed: true`) instead of posting twice.
* **Errors are predictable:** `401` no token · `403` missing permission · `404` not found · `422` validation (`errors`) or accounting rule (`message`) · `429` rate limit.

---

## Upgrading

After `composer update alimarchal/laravel-chart-of-accounts`:

```bash
php artisan accounting:update
```

Refreshes package-owned files (React pages / Select2 assets), runs new migrations (`--force`) and re-syncs the
database views and triggers. It never overwrites your `config/accounting.php` or customised views
(`--views` re-publishes Blade views explicitly).

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
    debitAccountCode: '5102',   // Rent Expense
    creditAccountCode: '1101',  // Cash In Hand
    amount: '150000.00',        // strings avoid float rounding
    post: false,                // save as draft
);

// Create and post immediately
$entry = JournalEntry::record(
    description: 'Salary payment — June 2026',
    debitAccountCode: '5101',   // Salary Expense
    creditAccountCode: '1108',  // Operating Bank Account
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
| `debitAccountCode` | `string` | Yes | Account code for the debit line (e.g. `'5101'`) |
| `creditAccountCode` | `string` | Yes | Account code for the credit line (e.g. `'1101'`) |
| `amount` | `float\|int\|string` | Yes | Amount — same value debited AND credited (rounded to cents) |
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
**Throws:** `ModelNotFoundException` if an account code or an open period for today is not found;
`AccountingException` if posting violates a rule (unbalanced, group/inactive account, closed period).

> `JournalEntry::record()` posts as the current user without a permission check — it is a server-side API.
> Authorize the caller yourself (e.g. `Gate::authorize('journal-entries.post')`) when exposing it to users.

---

### 2. REST API

**Create a draft entry:**
```http
POST /api/v1/accounting/journal-entries
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
POST /api/v1/accounting/journal-entries/{id}/post
Authorization: Bearer {token}
```

**Reverse entry** (`reversal_date` optional, defaults to today, cannot precede the original):
```http
POST /api/v1/accounting/journal-entries/{id}/reverse
Authorization: Bearer {token}
Content-Type: application/json

{ "description": "Reversal of SAL-2026-06", "reversal_date": "2026-06-30" }
```

`auto_post: true` on create/update additionally requires the `journal-entries.post` permission.

**Errors:** validation failures return `422` with `errors`; accounting rule violations (unbalanced entry,
closed period, already reversed, account in use, …) return `422` with a `message`.

**Void entry:**
```http
POST /api/v1/accounting/journal-entries/{id}/void
Authorization: Bearer {token}
```

**List with filters:**
```http
GET /api/v1/accounting/journal-entries?filter[status]=posted&filter[entry_date_from]=2026-06-01&sort=-entry_date
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

Base URL: `/api/v1/accounting` — `ACCOUNTING_API_PREFIX`; middleware `ACCOUNTING_API_MIDDLEWARE` (default `api,auth:sanctum`)
plus `throttle:accounting-api` (`ACCOUNTING_API_RATE_LIMIT`, default 120/min per user). Every endpoint checks a permission.
Machine-readable spec: [`docs/openapi.yaml`](docs/openapi.yaml).

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/health` | Installation health (503 if unhealthy) | `accounting.view` |
| GET/POST | `/journal-entries` | List (filters, sort, `include=lines`) / Create (+`auto_post`) | `journal-entries.view` / `.create` |
| POST | `/journal-entries/simple` | Two-line entry by account codes | `journal-entries.create` (+`.post`) |
| GET/PUT | `/journal-entries/{id}` | Show with lines / Update draft | `journal-entries.view` / `.update` |
| POST | `/journal-entries/{id}/post` | Post a draft | `journal-entries.post` |
| POST | `/journal-entries/{id}/reverse` | Reverse (optional `reversal_date`) | `journal-entries.reverse` |
| POST | `/journal-entries/{id}/void` | Void a draft | `journal-entries.void` |
| GET/POST | `/chart-of-accounts` | List / Create | `chart-of-accounts.view` / `.create` |
| GET | `/chart-of-accounts/tree` | Whole chart as a tree | `chart-of-accounts.view` |
| GET | `/chart-of-accounts/{id}/balance` | Balance as of a date (groups include children) | `chart-of-accounts.view` |
| GET/PUT/DELETE | `/chart-of-accounts/{id}` | Show / Update / Delete | `chart-of-accounts.*` |
| GET/POST, GET/PUT/DELETE | `/periods`, `/periods/{id}` | CRUD (no overlaps) | `periods.*` |
| POST | `/periods/{id}/close` · `/reopen` · `/close-fiscal-year` | Period workflow | `periods.close` / `periods.reopen` |
| GET | `/account-balance-snapshots[/{id}]` | Period-end snapshots | `account-balance-snapshots.view` |
| CRUD | `/account-types`, `/currencies`, `/cost-centers`, `/bank-accounts`, `/reconciliations`, `/tax-codes`, `/tax-rates` | Master data | `<resource>.view/create/update/delete` |
| GET | `/reports/trial-balance` · `balance-sheet` · `income-statement` · `general-ledger` · `cash-flow` · `bank-book` · `cash-book` · `aged-receivables` · `aged-payables` · `account-statement` | Financial reports (posted entries only) | `reports.<name>.view` |

List endpoints support `?filter[field]=value`, `?sort=field` / `-field`, `?page=N` and `?per_page=N` (capped by `ACCOUNTING_API_MAX_PER_PAGE`, default 100).

---

## Configuration

```bash
php artisan vendor:publish --tag=accounting-config
```

```php
// config/accounting.php (abridged)
return [
    'ui_driver'              => env('ACCOUNTING_UI_DRIVER', 'inertia'),   // 'inertia' | 'blade' | 'api'
    'route_prefix'           => env('ACCOUNTING_ROUTE_PREFIX', 'accounting'),
    'route_name_prefix'      => env('ACCOUNTING_ROUTE_NAME_PREFIX', 'accounting'),
    'settings_route_prefix'  => env('SETTINGS_ROUTE_PREFIX', 'settings'),
    'api_prefix'             => env('ACCOUNTING_API_PREFIX', 'api/v1/accounting'),
    'api_middleware'         => env('ACCOUNTING_API_MIDDLEWARE', 'api,auth:sanctum'), // comma separated
    'api_enabled'            => env('ACCOUNTING_API_ENABLED', true),
    'api_rate_limit'         => env('ACCOUNTING_API_RATE_LIMIT', 120),     // per minute per user; 0 = off
    'api_max_per_page'       => env('ACCOUNTING_API_MAX_PER_PAGE', 100),
    'users_table'            => env('ACCOUNTING_USERS_TABLE', 'users'),
    'defaults' => [
        'currency_code'                  => env('ACCOUNTING_BASE_CURRENCY', 'PKR'),
        'cash_account_code'              => env('ACCOUNTING_CASH_ACCOUNT_CODE', '1101'),   // + child accounts
        'bank_account_code'              => env('ACCOUNTING_BANK_ACCOUNT_CODE', '1102'),   // group: all bank accounts
        'retained_earnings_account_code' => env('ACCOUNTING_RETAINED_EARNINGS_ACCOUNT_CODE', '3101'),
        'rounding_account_code'          => env('ACCOUNTING_ROUNDING_ACCOUNT_CODE', '5201'),
    ],
    'aging' => [
        'receivable_account_codes' => ['1103', '1104'],
        'payable_account_codes'    => ['2101', '2102', '2103', '2104'],
    ],
    'chart_preset' => env('ACCOUNTING_CHART_PRESET', 'general'), // 'general' or 'school'
    'permissions'  => [/* … */],
    'roles'        => [/* super-admin, accountant, admin, viewer */],
];
```

Accounts referenced by `defaults.*_account_code` are protected: their code cannot change and they cannot be
deactivated or deleted.

The web routes always use `['web', 'auth', 'verified']` plus a per-route `can:` permission check.

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
| `accounting:close-period` | Close an accounting period (snapshots, totals, net income) |
| `accounting:open-period` | Reopen a closed accounting period |

---

## Models & Tables

| Model | Table | Description |
|-------|-------|-------------|
| `AccountType` | `accounting_account_types` | Asset, Liability, Equity, Revenue, Expense |
| `ChartOfAccount` | `accounting_chart_of_accounts` | Hierarchical account tree |
| `Currency` | `accounting_currencies` | Currencies and exchange rates |
| `AccountingPeriod` | `accounting_periods` | Fiscal periods with open/close state |
| `JournalEntry` | `accounting_journal_entries` | Entry header (draft / posted / void) |
| `JournalEntryLine` | `accounting_journal_entry_lines` | Debit/credit lines |
| `BankAccount` | `accounting_bank_accounts` | Bank account register |
| `Reconciliation` | `accounting_reconciliations` | Bank reconciliation records |
| `TaxCode` | `accounting_tax_codes` | Tax code definitions |
| `TaxRate` | `accounting_tax_rates` | Tax rates per code |
| `AccountingAuditLog` | `accounting_audit_logs` | Full change audit trail |
| `AccountBalanceSnapshot` | `accounting_account_balance_snapshots` | Period-end snapshots |
| `CostCenter` | `accounting_cost_centers` | Departmental cost centers |

---

## Roles & permissions

Six roles are seeded, designed around **segregation of duties** (the person who records an entry is not
the person who approves it):

| Role | Who | Can | Cannot |
|------|-----|-----|--------|
| `super-admin` | Owner | Everything | Approve **its own** entries under maker-checker |
| `admin` | IT / user admin | Users, roles, permissions | Record, post, approve or close anything |
| `accountant` | **Maker** | Record, edit, post (below the approval threshold), reverse, void drafts, close periods, bank & reconciliations, tax, FX rates | Approve, reopen periods, manage users/roles |
| `approver` | **Checker** | Approve or reject entries; read ledger & reports | Create, edit or post entries |
| `auditor` | Internal / external audit | Read everything incl. the audit trail | Change anything |
| `viewer` | Management | Read ledger & reports | Change anything |

```bash
php artisan accounting:roles            # who-can-what matrix from the database + SoD check
php artisan accounting:roles --config   # the shipped defaults
```

`accounting:roles` exits non-zero if any role other than `super-admin` can both create **and** approve
journal entries — handy as a deployment check.

**Safe to customise:** change roles in the Settings → Roles screen. Re-running `accounting:seed`
(e.g. after an upgrade) **never removes** permissions you changed — it only adds newly introduced ones.

**Privilege-escalation protection:** a user can only grant roles/permissions they hold themselves, only a
super-admin can manage super-admin users or the `super-admin` role, changing a user's roles requires
`user.assign-role` (direct permissions: `user.assign-permission`), role management requires
`accounting.manage-settings`.

<details>
<summary>Full role × permission matrix (defaults)</summary>

| Permission | super-admin | admin | accountant | approver | auditor | viewer |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `accounting.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `accounting.manage-settings` | ✔ | ✔ |  |  |  |  |
| `user.view` | ✔ | ✔ |  |  |  |  |
| `user.create` | ✔ | ✔ |  |  |  |  |
| `user.update` | ✔ | ✔ |  |  |  |  |
| `user.delete` | ✔ |  |  |  |  |  |
| `user.assign-role` | ✔ | ✔ |  |  |  |  |
| `user.assign-permission` | ✔ | ✔ |  |  |  |  |
| `account-types.view` | ✔ |  | ✔ |  | ✔ |  |
| `account-types.create` | ✔ |  |  |  |  |  |
| `account-types.update` | ✔ |  |  |  |  |  |
| `account-types.delete` | ✔ |  |  |  |  |  |
| `currencies.view` | ✔ |  | ✔ |  | ✔ |  |
| `currencies.create` | ✔ |  |  |  |  |  |
| `currencies.update` | ✔ |  | ✔ |  |  |  |
| `currencies.delete` | ✔ |  |  |  |  |  |
| `periods.view` | ✔ |  | ✔ |  | ✔ |  |
| `periods.create` | ✔ |  |  |  |  |  |
| `periods.update` | ✔ |  |  |  |  |  |
| `periods.delete` | ✔ |  |  |  |  |  |
| `periods.close` | ✔ |  | ✔ |  |  |  |
| `periods.reopen` | ✔ |  |  |  |  |  |
| `chart-of-accounts.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `chart-of-accounts.create` | ✔ |  |  |  |  |  |
| `chart-of-accounts.update` | ✔ |  |  |  |  |  |
| `chart-of-accounts.delete` | ✔ |  |  |  |  |  |
| `cost-centers.view` | ✔ |  | ✔ |  | ✔ |  |
| `cost-centers.create` | ✔ |  | ✔ |  |  |  |
| `cost-centers.update` | ✔ |  | ✔ |  |  |  |
| `cost-centers.delete` | ✔ |  |  |  |  |  |
| `journal-entries.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `journal-entries.create` | ✔ |  | ✔ |  |  |  |
| `journal-entries.update` | ✔ |  | ✔ |  |  |  |
| `journal-entries.delete` | ✔ |  |  |  |  |  |
| `journal-entries.post` | ✔ |  | ✔ |  |  |  |
| `journal-entries.reverse` | ✔ |  | ✔ |  |  |  |
| `journal-entries.void` | ✔ |  | ✔ |  |  |  |
| `journal-entries.approve` | ✔ |  |  | ✔ |  |  |
| `bank-accounts.view` | ✔ |  | ✔ |  | ✔ |  |
| `bank-accounts.create` | ✔ |  | ✔ |  |  |  |
| `bank-accounts.update` | ✔ |  | ✔ |  |  |  |
| `bank-accounts.delete` | ✔ |  |  |  |  |  |
| `reconciliations.view` | ✔ |  | ✔ |  | ✔ |  |
| `reconciliations.create` | ✔ |  | ✔ |  |  |  |
| `reconciliations.update` | ✔ |  | ✔ |  |  |  |
| `reconciliations.delete` | ✔ |  |  |  |  |  |
| `tax-codes.view` | ✔ |  | ✔ |  | ✔ | ✔ |
| `tax-codes.create` | ✔ |  | ✔ |  |  |  |
| `tax-codes.update` | ✔ |  | ✔ |  |  |  |
| `tax-codes.delete` | ✔ |  |  |  |  |  |
| `tax-rates.view` | ✔ |  | ✔ |  | ✔ | ✔ |
| `tax-rates.create` | ✔ |  | ✔ |  |  |  |
| `tax-rates.update` | ✔ |  | ✔ |  |  |  |
| `tax-rates.delete` | ✔ |  |  |  |  |  |
| `account-balance-snapshots.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.general-ledger.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.trial-balance.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `reports.balance-sheet.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `reports.income-statement.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `reports.cash-flow.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.aged-receivables.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.aged-payables.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.account-statement.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.account-balances.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.bank-book.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `reports.cash-book.view` | ✔ |  | ✔ | ✔ | ✔ | ✔ |
| `audit-logs.view` | ✔ |  | ✔ |  | ✔ |  |

</details>

---

## Select2 Integration

All `<select>` elements use **Select2 4.1.0** served from `public/vendor/accounting/`:

```
public/vendor/accounting/jquery.min.js       — jQuery 3.7.1
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
| **Draft** | Editable, not included in balances or reports |
| **Posted** | Locked (immutable in the database), included in balances; the period must be open |
| **Void** | Cancelled draft — posted entries cannot be voided, they must be reversed |

**Reversal:** reversing a posted entry posts a mirror entry (debits ↔ credits). Both stay `posted` and net to
zero (GAAP); the original is flagged via `reversed_by_entry_id` — use `$entry->isReversed()` or
`JournalEntry::reversed()`. A reversal cannot itself be reversed.

Integrity is enforced in layers:
1. **UI** — Save is disabled until debits equal credits.
2. **Application** — `PostJournalEntryAction` checks, in exact cents: ≥ 2 lines, each line either debit or
   credit, active posting (non-group) accounts, account currency, debits = credits, open period (row-locked).
3. **Database** — triggers reject any change to posted entries/lines (amounts, accounts, dates, status, deletes);
   PostgreSQL also has CHECK constraints (one-sided lines, positive FX rates, single base currency).

### Periods

- Periods may not overlap. A period's dates cannot change, and it cannot be deleted, once it contains entries.
- Closing requires `periods.close`, refuses while drafts are dated in the period, writes balance snapshots
  (opening, movement, closing) and stores revenue − expenses as `closing_net_income`.
- Reopening requires `periods.reopen`. `accounting:close-fiscal-year` additionally posts a closing entry that
  moves income-statement balances to retained earnings.

### Chart of accounts rules

- No cycles; a parent must be a **group** account of the **same account type**.
- Once an account has journal lines its code, type, normal balance and group flag are locked (rename or deactivate instead).
- Accounts with children or journal lines cannot be deleted; system accounts cannot be deleted.
- Omitted `is_active` / `is_group` on update are left unchanged; `normal_balance` defaults to the account type's.

### Seeding

`accounting:seed` only **creates missing** records — it never renames, re-activates or re-rates existing
accounts/currencies and never reopens closed periods. `ACCOUNTING_CHART_PRESET=general` (default) seeds
industry-neutral names; `school` seeds education-specific names. With a base currency other than PKR only the
base currency is seeded.

---

## Testing

```bash
composer test                     # Pest on in-memory SQLite
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=coa_test DB_USERNAME=postgres composer test
composer analyse                  # Larastan level 5
composer format:check             # Pint
```

---

## Production checklist

- Run `php artisan accounting:install --admin-email=…` and confirm who holds `super-admin`.
- Add `HasRoles` to your user model; keep `verified` email enforcement on.
- Protect the API with Sanctum (or set `ACCOUNTING_API_MIDDLEWARE`) and rate-limit it.
- Run `php artisan accounting:update` after every upgrade (re-syncs triggers and views).
- Back up before closing a fiscal year; restrict `periods.reopen` to a small group.
- Known limitations: single company per database (no tenant scoping); multi-currency stores an FX rate per
  entry but reports are in transaction amounts; the default exchange rates in the seeder are samples.

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

**Q: Why do I get a 422 "Posted journal entries are immutable"?**
A: Posted entries can't be edited or deleted — reverse them and post a corrected entry.

**Q: Why is the Save button disabled?**
A: Debits and credits must be equal before saving. Enter matching amounts in the debit/credit columns.

**Q: What is `accounting:update`?**
A: Refreshes package assets, runs new migrations and re-syncs DB objects without touching your config. Run after every `composer update`.

**Q: I only need the API — how do I keep it light?**
A: Set `ACCOUNTING_UI_DRIVER=api`. No web routes, views or Livewire components are loaded; only the 71 API routes.

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
