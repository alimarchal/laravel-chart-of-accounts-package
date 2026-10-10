# Help catalog: every screen and endpoint

Generated from the routes the package registers (`python3 docs/generate-route-catalog.py`); do not edit by hand.

- **381 web routes** (screens and the form actions behind them; the same screens exist for the React/Inertia and the Blade driver) and **311 API endpoints**.
- Web screens live under `/accounting` (users, roles and the feature switches under `/settings` with the Blade driver); the API under `/api/v1/accounting` (see the prefixes in `config/accounting.php`).
- *Permission* is the Spatie permission a user needs; a user without it gets 403. A feature that is switched off answers 404 (see *Feature switches* in the README).
- The API is described in full in [`openapi.yaml`](openapi.yaml) and the [Postman collection](postman_collection.json).

## Contents

- [Home and overview](#home-and-overview) (2)
- [Chart of accounts](#chart-of-accounts) (19)
- [Account types](#account-types) (8)
- [Currencies and exchange rates](#currencies-and-exchange-rates) (8)
- [Periods and closing](#periods-and-closing) (13)
- [Journal entries](#journal-entries) (16)
- [Reports](#reports) (14)
- [Cost centers](#cost-centers) (8)
- [Bank accounts](#bank-accounts) (8)
- [Reconciliations](#reconciliations) (10)
- [Tax codes and rates](#tax-codes-and-rates) (16)
- [Tax](#tax) (8)
- [Voucher types](#voucher-types) (5)
- [Control accounts](#control-accounts) (2)
- [Attachments](#attachments) (2)
- [Users and roles](#users-and-roles) (21)
- [Feature switches](#feature-switches) (2)
- [Companies](#companies) (7)
- [Language](#language) (1)
- [Audit log](#audit-log) (2)
- [My exports](#my-exports) (3)
- [Balance snapshots](#balance-snapshots) (2)
- [Recurring entries](#recurring-entries) (11)
- [Currency revaluation](#currency-revaluation) (7)
- [Bank statements](#bank-statements) (15)
- [Budgets](#budgets) (13)
- [Customers and suppliers](#customers-and-suppliers) (10)
- [Invoices and bills](#invoices-and-bills) (11)
- [Receipts and payments](#receipts-and-payments) (7)
- [Fixed assets](#fixed-assets) (12)
- [Inventory](#inventory) (17)
- [Payroll](#payroll) (92)
- [FBR invoices](#fbr-invoices) (2)
- [Chart templates](#chart-templates) (2)
- [Statement layout](#statement-layout) (5)
- [API endpoints](#api-endpoints)

## Home and overview

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting` |  | dashboard | `accounting.view` |
| GET | `/accounting/overview` |  | overview | `accounting.view` |

## Chart of accounts

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/chart-of-accounts` |  | List accounts | `chart-of-accounts.view` |
| POST | `/accounting/chart-of-accounts` |  | Create an account | `chart-of-accounts.create` |
| GET | `/accounting/chart-of-accounts/create` |  | new form — chart of accounts | `chart-of-accounts.create` |
| GET | `/accounting/chart-of-accounts/export/{format}` |  | Export the chart (re-importable) | `chart-of-accounts.view` |
| GET | `/accounting/chart-of-accounts/import` |  | import — chart of accounts | `chart-of-accounts.import` |
| POST | `/accounting/chart-of-accounts/import` |  | Import accounts from CSV or Excel (multipart/form-data) | `chart-of-accounts.import` |
| POST | `/accounting/chart-of-accounts/import/preview` |  | preview — chart of accounts / import | `chart-of-accounts.import` |
| GET | `/accounting/chart-of-accounts/import/template/{format}` |  | Import template with example rows | `chart-of-accounts.import` |
| GET | `/accounting/chart-of-accounts/tree` |  | Whole chart as a nested tree | `chart-of-accounts.view` |
| DELETE | `/accounting/chart-of-accounts/{chartOfAccount}` |  | Delete an account | `chart-of-accounts.delete` |
| GET | `—` | `/accounting/chart-of-accounts/{chartOfAccount}` | Show an account | `chart-of-accounts.view` |
| PATCH | `/accounting/chart-of-accounts/{chartOfAccount}` |  | save changes — chart of accounts | `chart-of-accounts.update` |
| PUT | `/accounting/chart-of-accounts/{chartOfAccount}` |  | Update an account (PATCH also accepted) | `chart-of-accounts.update` |
| PUT | `/accounting/chart-of-accounts/{chartOfAccount}/control-type` |  | Set or clear an account's control type | `control-accounts.manage` |
| GET | `/accounting/chart-of-accounts/{chartOfAccount}/edit` |  | edit form — chart of accounts | `chart-of-accounts.update` |
| POST | `/accounting/chart-of-accounts/{chartOfAccount}/merge` |  | Merge this account into another | `chart-of-accounts.restructure` |
| POST | `/accounting/chart-of-accounts/{chartOfAccount}/renumber` |  | Renumber an account (used accounts too) | `chart-of-accounts.restructure` |
| PUT | `/accounting/chart-of-accounts/{chartOfAccount}/report-mapping` |  | Map an account (and its sub-accounts) to a line | `report-mapping.manage` |
| GET | `/accounting/chart-of-accounts/{chartOfAccount}/restructure` |  | restructure — chart of accounts | `chart-of-accounts.restructure` |

## Account types

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/account-types` |  | List account-types | `account-types.view` |
| POST | `/accounting/account-types` |  | Create account-types | `account-types.create` |
| GET | `/accounting/account-types/create` |  | new form — account types | `account-types.create` |
| DELETE | `/accounting/account-types/{record}` |  | Delete account-types | `account-types.delete` |
| GET | `/accounting/account-types/{record}` |  | Show account-types | `account-types.view` |
| PATCH | `/accounting/account-types/{record}` |  | save changes — account types | `account-types.update` |
| PUT | `/accounting/account-types/{record}` |  | Update account-types (PATCH also accepted) | `account-types.update` |
| GET | `/accounting/account-types/{record}/edit` |  | edit form — account types | `account-types.update` |

## Currencies and exchange rates

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/currencies` |  | List currencies | `currencies.view` |
| POST | `/accounting/currencies` |  | Create currencies | `currencies.create` |
| GET | `/accounting/currencies/create` |  | new form — currencies | `currencies.create` |
| DELETE | `/accounting/currencies/{record}` |  | Delete currencies | `currencies.delete` |
| GET | `/accounting/currencies/{record}` |  | Show currencies | `currencies.view` |
| PATCH | `/accounting/currencies/{record}` |  | save changes — currencies | `currencies.update` |
| PUT | `/accounting/currencies/{record}` |  | Update currencies (PATCH also accepted) | `currencies.update` |
| GET | `/accounting/currencies/{record}/edit` |  | edit form — currencies | `currencies.update` |

## Periods and closing

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/periods` |  | List periods | `periods.view` |
| POST | `/accounting/periods` |  | Create periods | `periods.create` |
| GET | `/accounting/periods/create` |  | new form — periods | `periods.create` |
| POST | `/accounting/periods/generate-monthly` |  | Create twelve monthly periods | `periods.create` |
| GET | `/accounting/periods/{period}/close` |  | open — periods / close | `periods.view` |
| POST | `/accounting/periods/{period}/close` |  | Close a period | `periods.close` |
| POST | `/accounting/periods/{period}/close-fiscal-year` |  | Year-end close | `periods.close` |
| POST | `/accounting/periods/{period}/reopen` |  | Reopen a closed period | `periods.reopen` |
| DELETE | `/accounting/periods/{record}` | `/accounting/periods/{period}` | Delete periods | `periods.delete` |
| GET | `/accounting/periods/{record}` | `/accounting/periods/{period}` | Show periods | `periods.view` |
| PATCH | `/accounting/periods/{record}` | `/accounting/periods/{period}` | save changes — periods | `periods.update` |
| PUT | `/accounting/periods/{record}` | `/accounting/periods/{period}` | Update periods (PATCH also accepted) | `periods.update` |
| GET | `/accounting/periods/{record}/edit` | `/accounting/periods/{period}/edit` | edit form — periods | `periods.update` |

## Journal entries

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/journal-entries` |  | List entries | `journal-entries.view` |
| POST | `/accounting/journal-entries` |  | Create (and optionally post) an entry | `journal-entries.create` |
| GET | `/accounting/journal-entries/create` |  | new form — journal entries | `journal-entries.create` |
| GET | `/accounting/journal-entries/{journalEntry}` |  | Show an entry with lines | `journal-entries.view` |
| PATCH | `/accounting/journal-entries/{journalEntry}` |  | save changes — journal entries | `journal-entries.update` |
| PUT | `/accounting/journal-entries/{journalEntry}` |  | Update a draft (PATCH also accepted) | `journal-entries.update` |
| POST | `/accounting/journal-entries/{journalEntry}/approve` |  | Approve and post (checker) | `journal-entries.approve` |
| POST | `/accounting/journal-entries/{journalEntry}/attachments` |  | Attach a document (multipart/form-data) | `attachments.create` |
| GET | `/accounting/journal-entries/{journalEntry}/edit` |  | edit form — journal entries | `journal-entries.update` |
| GET | `/accounting/journal-entries/{journalEntry}/pdf` |  | Printable voucher PDF | `journal-entries.view` |
| POST | `/accounting/journal-entries/{journalEntry}/post` |  | Post a draft | `journal-entries.post` |
| GET | `/accounting/journal-entries/{journalEntry}/print` |  | print — journal entries | `journal-entries.view` |
| POST | `/accounting/journal-entries/{journalEntry}/reject` |  | Reject back to the maker (checker) | `journal-entries.approve` |
| POST | `/accounting/journal-entries/{journalEntry}/reverse` |  | Reverse a posted entry | `journal-entries.reverse` |
| POST | `/accounting/journal-entries/{journalEntry}/submit` |  | Submit a draft for approval (maker) | `journal-entries.create` |
| POST | `/accounting/journal-entries/{journalEntry}/void` |  | Void a draft | `journal-entries.void` |

## Reports

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `—` | `/accounting/reports/account-balances` | account balances — reports | `reports.account-balances.view` |
| GET | `/accounting/reports/account-statement` |  | Account statement | `reports.account-statement.view` |
| GET | `/accounting/reports/aged-payables` |  | Aged payables | `reports.aged-payables.view` |
| GET | `/accounting/reports/aged-receivables` |  | Aged receivables | `reports.aged-receivables.view` |
| GET | `/accounting/reports/balance-sheet` |  | Balance sheet | `reports.balance-sheet.view` |
| GET | `/accounting/reports/bank-book` |  | Bank book | `reports.bank-book.view` |
| GET | `/accounting/reports/cash-book` |  | Cash book | `reports.cash-book.view` |
| GET | `/accounting/reports/cash-flow` |  | Cash flow | `reports.cash-flow.view` |
| GET | `/accounting/reports/consolidated` |  | consolidated — reports | `reports.consolidated.view` |
| GET | `/accounting/reports/financial-statements` |  | financial statements — reports | `reports.financial-statements.view` |
| GET | `/accounting/reports/general-ledger` |  | General ledger | `reports.general-ledger.view` |
| GET | `/accounting/reports/income-statement` |  | Income statement | `reports.income-statement.view` |
| GET | `/accounting/reports/trial-balance` |  | Trial balance | `reports.trial-balance.view` |
| GET | `/accounting/reports/{report}/export/{format}` |  | Download a report as CSV, Excel or a typeset PDF | `accounting.view` |

## Cost centers

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/cost-centers` |  | List cost-centers | `cost-centers.view` |
| POST | `/accounting/cost-centers` |  | Create cost-centers | `cost-centers.create` |
| GET | `/accounting/cost-centers/create` |  | new form — cost centers | `cost-centers.create` |
| DELETE | `/accounting/cost-centers/{record}` |  | Delete cost-centers | `cost-centers.delete` |
| GET | `/accounting/cost-centers/{record}` |  | Show cost-centers | `cost-centers.view` |
| PATCH | `/accounting/cost-centers/{record}` |  | save changes — cost centers | `cost-centers.update` |
| PUT | `/accounting/cost-centers/{record}` |  | Update cost-centers (PATCH also accepted) | `cost-centers.update` |
| GET | `/accounting/cost-centers/{record}/edit` |  | edit form — cost centers | `cost-centers.update` |

## Bank accounts

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/bank-accounts` |  | List bank-accounts | `bank-accounts.view` |
| POST | `/accounting/bank-accounts` |  | Create bank-accounts | `bank-accounts.create` |
| GET | `/accounting/bank-accounts/create` |  | new form — bank accounts | `bank-accounts.create` |
| DELETE | `/accounting/bank-accounts/{record}` |  | Delete bank-accounts | `bank-accounts.delete` |
| GET | `/accounting/bank-accounts/{record}` |  | Show bank-accounts | `bank-accounts.view` |
| PATCH | `/accounting/bank-accounts/{record}` |  | save changes — bank accounts | `bank-accounts.update` |
| PUT | `/accounting/bank-accounts/{record}` |  | Update bank-accounts (PATCH also accepted) | `bank-accounts.update` |
| GET | `/accounting/bank-accounts/{record}/edit` |  | edit form — bank accounts | `bank-accounts.update` |

## Reconciliations

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/reconciliations` |  | List reconciliations | `reconciliations.view` |
| POST | `/accounting/reconciliations` |  | Create reconciliations | `reconciliations.create` |
| GET | `/accounting/reconciliations/create` |  | new form — reconciliations | `reconciliations.create` |
| GET | `/accounting/reconciliations/{reconciliation}/match` |  | match — reconciliations | `reconciliations.update` |
| POST | `/accounting/reconciliations/{reconciliation}/reconcile` |  | reconcile — reconciliations | `reconciliations.update` |
| DELETE | `/accounting/reconciliations/{record}` |  | Delete reconciliations | `reconciliations.delete` |
| GET | `/accounting/reconciliations/{record}` |  | Show reconciliations | `reconciliations.view` |
| PATCH | `/accounting/reconciliations/{record}` |  | save changes — reconciliations | `reconciliations.update` |
| PUT | `/accounting/reconciliations/{record}` |  | Update reconciliations (PATCH also accepted) | `reconciliations.update` |
| GET | `/accounting/reconciliations/{record}/edit` |  | edit form — reconciliations | `reconciliations.update` |

## Tax codes and rates

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/tax-codes` |  | List tax-codes | `tax-codes.view` |
| POST | `/accounting/tax-codes` |  | Create tax-codes | `tax-codes.create` |
| GET | `/accounting/tax-codes/create` |  | new form — tax codes | `tax-codes.create` |
| DELETE | `/accounting/tax-codes/{record}` |  | Delete tax-codes | `tax-codes.delete` |
| GET | `/accounting/tax-codes/{record}` |  | Show tax-codes | `tax-codes.view` |
| PATCH | `/accounting/tax-codes/{record}` |  | save changes — tax codes | `tax-codes.update` |
| PUT | `/accounting/tax-codes/{record}` |  | Update tax-codes (PATCH also accepted) | `tax-codes.update` |
| GET | `/accounting/tax-codes/{record}/edit` |  | edit form — tax codes | `tax-codes.update` |
| GET | `/accounting/tax-rates` |  | List tax-rates | `tax-rates.view` |
| POST | `/accounting/tax-rates` |  | Create tax-rates | `tax-rates.create` |
| GET | `/accounting/tax-rates/create` |  | new form — tax rates | `tax-rates.create` |
| DELETE | `/accounting/tax-rates/{record}` |  | Delete tax-rates | `tax-rates.delete` |
| GET | `/accounting/tax-rates/{record}` |  | Show tax-rates | `tax-rates.view` |
| PATCH | `/accounting/tax-rates/{record}` |  | save changes — tax rates | `tax-rates.update` |
| PUT | `/accounting/tax-rates/{record}` |  | Update tax-rates (PATCH also accepted) | `tax-rates.update` |
| GET | `/accounting/tax-rates/{record}/edit` |  | edit form — tax rates | `tax-rates.update` |

## Tax

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/tax` |  | list — tax | `tax-returns.view` |
| POST | `/accounting/tax/calculate` |  | Tax on an amount | `tax-codes.view` |
| POST | `/accounting/tax/entries` |  | Book a taxed document as a journal entry | `tax-entries.create` |
| GET | `/accounting/tax/entries/create` |  | new form — tax / entries | `tax-entries.create` |
| POST | `/accounting/tax/returns` |  | File a return for a period | `tax-returns.file` |
| GET | `/accounting/tax/returns/report` |  | The tax ledger of a period | `tax-returns.view` |
| GET | `/accounting/tax/returns/report/export/{format}` |  | Export the tax report | `tax-returns.view` |
| DELETE | `/accounting/tax/returns/{taxReturn}` |  | Void a return (its entry is reversed) | `tax-returns.file` |

## Voucher types

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/voucher-types` |  | List voucher types with their next numbers | `voucher-types.view` |
| POST | `/accounting/voucher-types` |  | Create a voucher type | `voucher-types.create` |
| DELETE | `/accounting/voucher-types/{record}` |  | Delete an unused voucher type | `voucher-types.delete` |
| PATCH | `/accounting/voucher-types/{record}` |  | save changes — voucher types | `voucher-types.update` |
| PUT | `/accounting/voucher-types/{record}` |  | Update a voucher type (PATCH also accepted) | `voucher-types.update` |

## Control accounts

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/control-accounts` |  | Control accounts with balances and manual postings | `chart-of-accounts.view` |
| POST | `/accounting/control-accounts/recommended` |  | Mark the recommended control accounts | `control-accounts.manage` |

## Attachments

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| DELETE | `/accounting/attachments/{attachment}` |  | Remove a document of a draft | `attachments.delete` |
| GET | `/accounting/attachments/{attachment}/download` |  | Download a document | `attachments.view` |

## Users and roles

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/roles` | `/settings/roles` | List roles with permission and user counts | `accounting.manage-settings` |
| POST | `/accounting/roles` | `/settings/roles` | Create a role | `accounting.manage-settings` |
| GET | `/accounting/roles/create` | `/settings/roles/create` | new form — roles | `accounting.manage-settings` |
| DELETE | `/accounting/roles/{role}` | `/settings/roles/{role}` | Delete a role | `accounting.manage-settings` |
| PATCH | `/accounting/roles/{role}` | `/settings/roles/{role}` | save changes — roles | `accounting.manage-settings` |
| PUT | `/accounting/roles/{role}` | `/settings/roles/{role}` | Update a role (PATCH also accepted) | `accounting.manage-settings` |
| GET | `/accounting/roles/{role}/edit` | `/settings/roles/{role}/edit` | edit form — roles | `accounting.manage-settings` |
| GET | `/accounting/users` | `/settings/users` | List users | `user.view` |
| POST | `/accounting/users` | `/settings/users` | Create a user | `user.create` |
| GET | `/accounting/users/create` | `/settings/users/create` | new form — users | `user.create` |
| DELETE | `/accounting/users/{user}` | `/settings/users/{user}` | Delete a user | `user.delete` |
| PATCH | `/accounting/users/{user}` | `/settings/users/{user}` | save changes — users | `user.update` |
| PUT | `/accounting/users/{user}` | `/settings/users/{user}` | Update a user (PATCH also accepted) | `user.update` |
| GET | `/accounting/users/{user}/edit` | `/settings/users/{user}/edit` | edit form — users | `user.update` |
| PUT | `/accounting/users/{user}/permissions` |  | Replace a user's direct permissions | `user.assign-permission` |
| GET | `—` | `/settings/permissions` | list — permissions | `accounting.manage-settings` |
| GET | `—` | `/settings/permissions/{permission}` | open — permissions | `accounting.manage-settings` |
| GET | `—` | `/settings/roles/{role}` | open — roles | `accounting.manage-settings` |
| GET | `—` | `/settings/users/{user}` | open — users | `user.view` |
| GET | `—` | `/settings/users/{user}/permissions` | edit form — users / permissions | `user.assign-permission` |
| POST | `—` | `/settings/users/{user}/permissions` | sync — users / permissions | `user.assign-permission` |

## Feature switches

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/features` | `/settings/features` | Every optional module and payroll feature with its switch | `accounting.manage-settings` |
| PUT | `/accounting/features` | `/settings/features` | Switch features on or off | `accounting.manage-settings` |

## Companies

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/companies` |  | Companies you can work in | `companies.manage` |
| POST | `/accounting/companies` |  | Create a company | `companies.manage` |
| PATCH | `/accounting/companies/{company}` |  | save changes — companies | `companies.manage` |
| PUT | `/accounting/companies/{company}` |  | Update a company | `companies.manage` |
| POST | `/accounting/companies/{company}/users` |  | Give a user access | `companies.manage` |
| DELETE | `/accounting/companies/{company}/users/{user}` |  | Remove a user's access | `companies.manage` |
| POST | `/accounting/company/switch` |  | switch — company | `accounting.view` |

## Language

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| POST | `/accounting/locale` |  | locale | `accounting.view` |

## Audit log

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/audit-logs` |  | list — audit logs | `audit-logs.view` |
| GET | `/accounting/audit-logs/{record}` |  | open — audit logs | `audit-logs.view` |

## My exports

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/exports` |  | Your background exports | `accounting.view` |
| DELETE | `/accounting/exports/{export}` |  | Delete one of your exports and its file | `accounting.view` |
| GET | `/accounting/exports/{export}/download` |  | Download a finished export | `accounting.view` |

## Balance snapshots

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/account-balance-snapshots` |  | List balance snapshots | `account-balance-snapshots.view` |
| GET | `/accounting/account-balance-snapshots/{record}` |  | Show a balance snapshot | `account-balance-snapshots.view` |

## Recurring entries

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/recurring-entries` |  | Recurring entry templates | `recurring-entries.view` |
| POST | `/accounting/recurring-entries` |  | Create a template | `recurring-entries.create` |
| GET | `/accounting/recurring-entries/create` |  | new form — recurring entries | `recurring-entries.create` |
| DELETE | `/accounting/recurring-entries/{recurringEntry}` |  | Delete a template that has not generated anything | `recurring-entries.delete` |
| GET | `/accounting/recurring-entries/{recurringEntry}` |  | A template with its generated entries and upcoming dates | `recurring-entries.view` |
| PATCH | `/accounting/recurring-entries/{recurringEntry}` |  | save changes — recurring entries | `recurring-entries.update` |
| PUT | `/accounting/recurring-entries/{recurringEntry}` |  | Change a template (PATCH also accepted) | `recurring-entries.update` |
| GET | `/accounting/recurring-entries/{recurringEntry}/edit` |  | edit form — recurring entries | `recurring-entries.update` |
| POST | `/accounting/recurring-entries/{recurringEntry}/pause` |  | Pause a template | `recurring-entries.update` |
| POST | `/accounting/recurring-entries/{recurringEntry}/resume` |  | Resume a paused template | `recurring-entries.update` |
| POST | `/accounting/recurring-entries/{recurringEntry}/run` |  | Generate the next entry now | `recurring-entries.run` |

## Currency revaluation

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/fx-revaluation` |  | Past revaluations and the dated exchange rates | `fx-revaluation.view` |
| POST | `/accounting/fx-revaluation` |  | Post a revaluation | `fx-revaluation.run` |
| GET | `/accounting/fx-revaluation/preview` |  | What a revaluation would adjust (nothing is posted) | `fx-revaluation.view` |
| POST | `/accounting/fx-revaluation/rates` |  | Save a dated exchange rate (replaces the rate of that currency and date) | `fx-revaluation.rates` |
| DELETE | `/accounting/fx-revaluation/rates/{exchangeRate}` |  | Remove a dated rate | `fx-revaluation.rates` |
| GET | `/accounting/fx-revaluation/{fxRevaluation}` |  | One revaluation with its accounts | `fx-revaluation.view` |
| POST | `/accounting/fx-revaluation/{fxRevaluation}/reverse` |  | Reverse a revaluation | `fx-revaluation.run` |

## Bank statements

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/bank-statement-lines/{bankStatementLine}/candidates` |  | Ledger lines that could be this transaction | `bank-statements.view` |
| POST | `/accounting/bank-statement-lines/{bankStatementLine}/create-entry` |  | Book a transaction as a journal entry | `bank-statements.match` |
| POST | `/accounting/bank-statement-lines/{bankStatementLine}/ignore` |  | Ignore (or restore) a transaction | `bank-statements.match` |
| POST | `/accounting/bank-statement-lines/{bankStatementLine}/match` |  | Match a transaction to a ledger line | `bank-statements.match` |
| POST | `/accounting/bank-statement-lines/{bankStatementLine}/unmatch` |  | Release a matched or booked transaction | `bank-statements.match` |
| GET | `/accounting/bank-statements` |  | Imported statements | `bank-statements.view` |
| GET | `/accounting/bank-statements/import` |  | import — bank statements | `bank-statements.import` |
| POST | `/accounting/bank-statements/import` |  | Import a statement (CSV or XLSX) | `bank-statements.import` |
| POST | `/accounting/bank-statements/import/preview` |  | preview — bank statements / import | `bank-statements.import` |
| DELETE | `/accounting/bank-statements/{bankStatement}` |  | Delete a statement and release its matches | `bank-statements.match` |
| GET | `/accounting/bank-statements/{bankStatement}` |  | A statement with its transactions | `bank-statements.view` |
| PATCH | `/accounting/bank-statements/{bankStatement}` |  | save changes — bank statements | `bank-statements.match` |
| PUT | `/accounting/bank-statements/{bankStatement}` |  | Set the closing balance (PATCH also accepted) | `bank-statements.match` |
| POST | `/accounting/bank-statements/{bankStatement}/auto-match` |  | Match every transaction that has exactly one ledger candidate | `bank-statements.match` |
| POST | `/accounting/bank-statements/{bankStatement}/reconcile` |  | Reconcile the statement | `bank-statements.match` |

## Budgets

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/budgets` |  | Budgets | `budgets.view` |
| POST | `/accounting/budgets` |  | Create a draft budget | `budgets.create` |
| GET | `/accounting/budgets/create` |  | new form — budgets | `budgets.create` |
| DELETE | `/accounting/budgets/{budget}` |  | Delete a budget that is not approved | `budgets.delete` |
| GET | `/accounting/budgets/{budget}` |  | Budget against actual | `budgets.view` |
| PATCH | `/accounting/budgets/{budget}` |  | save changes — budgets | `budgets.update` |
| PUT | `/accounting/budgets/{budget}` |  | Change a draft budget (PATCH also accepted) | `budgets.update` |
| POST | `/accounting/budgets/{budget}/approve` |  | Approve a draft budget | `budgets.approve` |
| POST | `/accounting/budgets/{budget}/close` |  | Close an approved budget | `budgets.approve` |
| POST | `/accounting/budgets/{budget}/copy` |  | Copy as a draft, optionally shifted and raised | `budgets.create` |
| GET | `/accounting/budgets/{budget}/edit` |  | edit form — budgets | `budgets.update` |
| GET | `/accounting/budgets/{budget}/export/{format}` |  | Export budget against actual | `budgets.view` |
| POST | `/accounting/budgets/{budget}/reopen` |  | Reopen an approved or closed budget as a draft | `budgets.update` |

## Customers and suppliers

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/parties` |  | Customers and suppliers | `parties.view` |
| POST | `/accounting/parties` |  | Create a customer or supplier | `parties.create` |
| GET | `/accounting/parties/create` |  | new form — parties | `parties.create` |
| DELETE | `/accounting/parties/{party}` |  | Delete a party without documents or payments | `parties.delete` |
| GET | `/accounting/parties/{party}` |  | A party with its balance and open items | `parties.view` |
| PATCH | `/accounting/parties/{party}` |  | save changes — parties | `parties.update` |
| PUT | `/accounting/parties/{party}` |  | Change a party (PATCH also accepted) | `parties.update` |
| GET | `/accounting/parties/{party}/edit` |  | edit form — parties | `parties.update` |
| GET | `/accounting/receivables/aging` |  | Ageing by customer or supplier, checked against the control account | `party-documents.view` |
| GET | `/accounting/receivables/aging/export/{format}` |  | Export the ageing | `party-documents.view` |

## Invoices and bills

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/party-documents` |  | Invoices, bills, credit and debit notes | `party-documents.view` |
| POST | `/accounting/party-documents` |  | Create a draft document | `party-documents.create` |
| GET | `/accounting/party-documents/create` |  | new form — party documents | `party-documents.create` |
| DELETE | `/accounting/party-documents/{partyDocument}` |  | Delete a draft | `party-documents.delete` |
| GET | `/accounting/party-documents/{partyDocument}` |  | A document with its lines | `party-documents.view` |
| PATCH | `/accounting/party-documents/{partyDocument}` |  | save changes — party documents | `party-documents.update` |
| PUT | `/accounting/party-documents/{partyDocument}` |  | Change a draft (PATCH also accepted) | `party-documents.update` |
| POST | `/accounting/party-documents/{partyDocument}/apply` |  | Apply a credit or debit note to invoices or bills | `party-documents.post` |
| GET | `/accounting/party-documents/{partyDocument}/edit` |  | edit form — party documents | `party-documents.update` |
| POST | `/accounting/party-documents/{partyDocument}/post` |  | Number and post a draft | `party-documents.post` |
| POST | `/accounting/party-documents/{partyDocument}/void` |  | Void a posted document (its entry is reversed) | `party-documents.void` |

## Receipts and payments

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| DELETE | `/accounting/party-allocations/{partyAllocation}` |  | Undo an allocation | `party-payments.create` |
| GET | `/accounting/party-payments` |  | Receipts and payments | `party-payments.view` |
| POST | `/accounting/party-payments` |  | Record a receipt from a customer or a payment to a supplier | `party-payments.create` |
| GET | `/accounting/party-payments/create` |  | new form — party payments | `party-payments.create` |
| GET | `/accounting/party-payments/{partyPayment}` |  | A payment with its allocations | `party-payments.view` |
| POST | `/accounting/party-payments/{partyPayment}/allocate` |  | Allocate (more of) a payment to documents | `party-payments.create` |
| POST | `/accounting/party-payments/{partyPayment}/void` |  | Void a payment (entry reversed, allocations released) | `party-payments.void` |

## Fixed assets

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/fixed-assets` |  | Fixed asset register at a date | `fixed-assets.view` |
| POST | `/accounting/fixed-assets` |  | Register an asset | `fixed-assets.create` |
| GET | `/accounting/fixed-assets/create` |  | new form — fixed assets | `fixed-assets.create` |
| GET | `/accounting/fixed-assets/depreciation` |  | Preview the depreciation due | `fixed-assets.view` |
| POST | `/accounting/fixed-assets/depreciation` |  | Book the depreciation due | `fixed-assets.depreciate` |
| GET | `/accounting/fixed-assets/export/{format}` |  | Export the register | `fixed-assets.view` |
| DELETE | `/accounting/fixed-assets/{asset}` |  | Delete an asset without entries | `fixed-assets.delete` |
| GET | `/accounting/fixed-assets/{asset}` |  | An asset with its depreciation history | `fixed-assets.view` |
| PATCH | `/accounting/fixed-assets/{asset}` |  | save changes — fixed assets | `fixed-assets.update` |
| PUT | `/accounting/fixed-assets/{asset}` |  | Change an asset (PATCH also accepted) | `fixed-assets.update` |
| POST | `/accounting/fixed-assets/{asset}/dispose` |  | Sell or scrap an asset | `fixed-assets.dispose` |
| GET | `/accounting/fixed-assets/{asset}/edit` |  | edit form — fixed assets | `fixed-assets.update` |

## Inventory

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/inventory` |  | Stock valuation at a date | `inventory.view` |
| GET | `/accounting/inventory/export/{format}` |  | Export the stock valuation | `inventory.view` |
| POST | `/accounting/inventory/items` |  | Create an item | `inventory.manage` |
| GET | `/accounting/inventory/items/create` |  | new form — inventory / items | `inventory.manage` |
| DELETE | `/accounting/inventory/items/{item}` |  | Delete an item without movements | `inventory.manage` |
| GET | `/accounting/inventory/items/{item}` |  | An item with its stock card | `inventory.view` |
| PATCH | `/accounting/inventory/items/{item}` |  | save changes — inventory / items | `inventory.manage` |
| PUT | `/accounting/inventory/items/{item}` |  | Change an item (PATCH also accepted) | `inventory.manage` |
| GET | `/accounting/inventory/items/{item}/edit` |  | edit form — inventory / items | `inventory.manage` |
| GET | `/accounting/inventory/movements` |  | The latest 200 stock movements | `inventory.view` |
| POST | `/accounting/inventory/movements` |  | Receive, issue, adjust or transfer stock | `inventory.move` |
| GET | `/accounting/inventory/movements/create` |  | new form — inventory / movements | `inventory.move` |
| GET | `/accounting/inventory/warehouses` |  | Warehouses | `inventory.view` |
| POST | `/accounting/inventory/warehouses` |  | Add a warehouse | `inventory.manage` |
| DELETE | `/accounting/inventory/warehouses/{warehouse}` |  | Delete a warehouse without movements | `inventory.manage` |
| PATCH | `/accounting/inventory/warehouses/{warehouse}` |  | save changes — inventory / warehouses | `inventory.manage` |
| PUT | `/accounting/inventory/warehouses/{warehouse}` |  | Change a warehouse (PATCH also accepted) | `inventory.manage` |

## Payroll

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/payroll` |  | Payroll runs, newest first | `payroll.view` |
| GET | `/accounting/payroll/adjustments` |  | Bonuses and other one-off pay of a month | `payroll.view` |
| POST | `/accounting/payroll/adjustments` |  | Add a bonus, extra allowance or fine for one employee and month | `payroll.manage` |
| POST | `/accounting/payroll/adjustments/bulk` |  | The same bonus for every active employee (or the ones named) | `payroll.manage` |
| POST | `/accounting/payroll/adjustments/{adjustment}/cancel` |  | Cancel an adjustment no run has taken up | `payroll.manage` |
| GET | `/accounting/payroll/arrears` |  | The arrears register with totals | `payroll.view` |
| POST | `/accounting/payroll/arrears` |  | Create arrears (drafts) from the posted payslips | `payroll.run` |
| POST | `/accounting/payroll/arrears/approve-all` |  | Approve every arrears draft | `payroll.post` |
| POST | `/accounting/payroll/arrears/preview` |  | Work arrears out without saving | `payroll.run` |
| GET | `/accounting/payroll/arrears/{arrear}` |  | One arrears record with its months | `payroll.view` |
| POST | `/accounting/payroll/arrears/{arrear}/approve` |  | Approve arrears | `payroll.post` |
| POST | `/accounting/payroll/arrears/{arrear}/cancel` |  | Cancel arrears not yet in a run | `payroll.post` |
| GET | `/accounting/payroll/attendance` |  | The attendance sheet of a month | `payroll.view` |
| POST | `/accounting/payroll/attendance` |  | Save the attendance sheet of a month | `payroll.manage` |
| GET | `/accounting/payroll/bulk` |  | bulk — payroll | `payroll.manage` |
| POST | `/accounting/payroll/bulk/components` |  | Give a component to a group of employees, or take it away | `payroll.manage` |
| GET | `/accounting/payroll/components` |  | Allowances and deductions | `payroll.view` |
| POST | `/accounting/payroll/components` |  | Add an allowance or deduction | `payroll.manage` |
| DELETE | `/accounting/payroll/components/{component}` |  | Delete a component that is not in use | `payroll.manage` |
| PATCH | `/accounting/payroll/components/{component}` |  | save changes — payroll / components | `payroll.manage` |
| PUT | `/accounting/payroll/components/{component}` |  | Change a component (PATCH also accepted) | `payroll.manage` |
| GET | `/accounting/payroll/employees` |  | Employees | `payroll.view` |
| POST | `/accounting/payroll/employees` |  | Add an employee | `payroll.manage` |
| GET | `/accounting/payroll/employees/create` |  | new form — payroll / employees | `payroll.manage` |
| DELETE | `/accounting/payroll/employees/{employee}` |  | Delete an employee without payslips | `payroll.manage` |
| PATCH | `/accounting/payroll/employees/{employee}` |  | save changes — payroll / employees | `payroll.manage` |
| PUT | `/accounting/payroll/employees/{employee}` |  | Change an employee (PATCH also accepted) | `payroll.manage` |
| GET | `/accounting/payroll/employees/{employee}/edit` |  | edit form — payroll / employees | `payroll.manage` |
| GET | `/accounting/payroll/employees/{employee}/revisions` |  | The salary history of an employee | `payroll.view` |
| POST | `/accounting/payroll/employees/{employee}/revisions` |  | Revise an employee's salary from a date | `payroll.manage` |
| GET | `/accounting/payroll/grades` |  | Salary grades | `payroll.view` |
| POST | `/accounting/payroll/grades` |  | Add a salary grade | `payroll.manage` |
| DELETE | `/accounting/payroll/grades/{grade}` |  | Delete a grade nobody is on | `payroll.manage` |
| GET | `/accounting/payroll/grades/{grade}` |  | A grade with its allowances | `payroll.view` |
| PATCH | `/accounting/payroll/grades/{grade}` |  | save changes — payroll / grades | `payroll.manage` |
| PUT | `/accounting/payroll/grades/{grade}` |  | Change a grade (PATCH also accepted) | `payroll.manage` |
| POST | `/accounting/payroll/grades/{grade}/assign` |  | Put a group of employees on the grade | `payroll.manage` |
| GET | `/accounting/payroll/leave-types` |  | Leave types | `payroll.view` |
| POST | `/accounting/payroll/leave-types` |  | Add a leave type | `payroll.manage` |
| DELETE | `/accounting/payroll/leave-types/{leaveType}` |  | Delete a leave type nobody took | `payroll.manage` |
| PATCH | `/accounting/payroll/leave-types/{leaveType}` |  | save changes — payroll / leave types | `payroll.manage` |
| PUT | `/accounting/payroll/leave-types/{leaveType}` |  | Change a leave type (PATCH also accepted) | `payroll.manage` |
| GET | `/accounting/payroll/leaves` |  | Leaves, newest first | `payroll.view` |
| POST | `/accounting/payroll/leaves` |  | Record leave | `payroll.manage` |
| GET | `/accounting/payroll/leaves/balances` |  | Leave balances of a year | `payroll.view` |
| POST | `/accounting/payroll/leaves/{leave}/cancel` |  | Cancel a leave | `payroll.manage` |
| GET | `/accounting/payroll/loans` |  | Loans and advances | `payroll.view` |
| POST | `/accounting/payroll/loans` |  | Record a loan or advance with its instalment schedule | `payroll.manage` |
| GET | `/accounting/payroll/loans/{loan}` |  | A loan with its schedule | `payroll.view` |
| POST | `/accounting/payroll/loans/{loan}/cancel` |  | Cancel a loan not yet paid out | `payroll.manage` |
| POST | `/accounting/payroll/loans/{loan}/disburse` |  | Pay the loan out | `payroll.post` |
| POST | `/accounting/payroll/loans/{loan}/settle` |  | The employee pays back what is left in cash | `payroll.post` |
| POST | `/accounting/payroll/loans/{loan}/skip` |  | Move the next instalment to the end of the schedule | `payroll.manage` |
| GET | `/accounting/payroll/reports` |  | Month comparison, cost centers and headcount | `payroll.view` |
| GET | `/accounting/payroll/reports/{report}/export/{format}` |  | Download a payroll report | `payroll.view` |
| POST | `/accounting/payroll/revisions` |  | Apply a raise to a group | `payroll.manage` |
| POST | `/accounting/payroll/revisions/preview` |  | Preview a raise for a group (nothing is saved) | `payroll.manage` |
| POST | `/accounting/payroll/runs` |  | Start a payroll run for a month | `payroll.run` |
| DELETE | `/accounting/payroll/runs/{run}` |  | Delete a draft run | `payroll.run` |
| GET | `/accounting/payroll/runs/{run}` |  | A run with its payslips and their lines | `payroll.view` |
| POST | `/accounting/payroll/runs/{run}/approve` |  | Finance approves a submitted run | `payroll.approve` |
| GET | `/accounting/payroll/runs/{run}/bank-file/{format}` |  | The bank salary file of a posted run | `payroll.post` |
| POST | `/accounting/payroll/runs/{run}/email-payslips` |  | E-mail every payslip of a posted run | `payroll.manage` |
| POST | `/accounting/payroll/runs/{run}/pay` |  | Pay the net salaries | `payroll.post` |
| GET | `/accounting/payroll/runs/{run}/payslips/{payslip}` |  | One payslip | `payroll.view` |
| POST | `/accounting/payroll/runs/{run}/payslips/{payslip}/email` |  | E-mail one payslip to the employee | `payroll.manage` |
| GET | `/accounting/payroll/runs/{run}/payslips/{payslip}/pdf` |  | A payslip as a PDF | `payroll.view` |
| GET | `/accounting/payroll/runs/{run}/payslips/{payslip}/print` |  | A payslip as a printable page | `payroll.view` |
| POST | `/accounting/payroll/runs/{run}/post` |  | Post the run to the books | `payroll.post` |
| POST | `/accounting/payroll/runs/{run}/recalculate` |  | Work the payslips out again (draft runs) | `payroll.run` |
| POST | `/accounting/payroll/runs/{run}/reject` |  | Finance sends a submitted run back to HR | `payroll.approve` |
| POST | `/accounting/payroll/runs/{run}/submit` |  | HR submits a draft run to finance | `payroll.run` |
| POST | `/accounting/payroll/runs/{run}/void` |  | Void a posted or paid run | `payroll.void` |
| POST | `/accounting/payroll/runs/{run}/withdraw` |  | Take a submitted or approved run back to draft | `payroll.run` |
| GET | `/accounting/payroll/schemes` |  | Contribution schemes (EOBI, PESSI/SESSI, provident fund) | `payroll.view` |
| POST | `/accounting/payroll/schemes` |  | Add a contribution scheme | `payroll.manage` |
| DELETE | `/accounting/payroll/schemes/{scheme}` |  | Delete a scheme | `payroll.manage` |
| GET | `/accounting/payroll/schemes/{scheme}` |  | A scheme | `payroll.view` |
| PATCH | `/accounting/payroll/schemes/{scheme}` |  | save changes — payroll / schemes | `payroll.manage` |
| PUT | `/accounting/payroll/schemes/{scheme}` |  | Change a scheme (PATCH also accepted) | `payroll.manage` |
| POST | `/accounting/payroll/schemes/{scheme}/assign` |  | Give a scheme to employees or take it away | `payroll.manage` |
| GET | `/accounting/payroll/settlements` |  | Final settlements | `payroll.view` |
| POST | `/accounting/payroll/settlements` |  | Save a settlement as a draft | `payroll.manage` |
| POST | `/accounting/payroll/settlements/preview` |  | Work a settlement out without saving | `payroll.manage` |
| DELETE | `/accounting/payroll/settlements/{settlement}` |  | Delete a draft settlement | `payroll.manage` |
| GET | `/accounting/payroll/settlements/{settlement}` |  | One settlement | `payroll.view` |
| POST | `/accounting/payroll/settlements/{settlement}/pay` |  | Pay a posted settlement | `payroll.post` |
| POST | `/accounting/payroll/settlements/{settlement}/post` |  | Post a settlement to the books | `payroll.post` |
| POST | `/accounting/payroll/settlements/{settlement}/void` |  | Void a settlement | `payroll.void` |
| GET | `/accounting/payroll/tax` |  | Salary and tax withheld per employee for a tax year | `payroll.view` |
| GET | `/accounting/payroll/tax/annual/{format}` |  | Download the annual salary tax statement | `payroll.view` |
| GET | `/accounting/payroll/tax/certificate/{employee}` |  | An employee's salary tax certificate | `payroll.view` |

## FBR invoices

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/fbr` |  | Posted sales invoices and credit notes with their FBR status | `fbr.view` |
| POST | `/accounting/fbr/documents/{document}/submit` |  | Send a sales invoice or credit note to FBR (or retry a failed one) | `fbr.submit` |

## Chart templates

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| GET | `/accounting/chart-templates` |  | Industry chart templates | `chart-templates.apply` |
| POST | `/accounting/chart-templates/{template}/apply` |  | Add a template's accounts to this company | `chart-templates.apply` |

## Statement layout

| Method | Screen or action (React) | Blade driver | What it does | Permission |
|--------|--------|--------------|--------------|------------|
| POST | `/accounting/report-lines` |  | Add a statement line | `report-mapping.manage` |
| DELETE | `/accounting/report-lines/{reportLine}` |  | Delete a custom line | `report-mapping.manage` |
| PUT | `/accounting/report-lines/{reportLine}` |  | Rename, move or reclassify a line | `report-mapping.manage` |
| GET | `/accounting/report-mapping` |  | Report lines and the mapping of every account | `report-mapping.manage` |
| POST | `/accounting/report-mapping/recommended` |  | Map the seeded accounts to their standard lines | `report-mapping.manage` |

## API endpoints

Base URL `/api/v1/accounting`; every request needs a Sanctum token (`Authorization: Bearer ...`).

### Journal entries

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/journal-entries/{journalEntry}/pdf` | Printable voucher PDF | `journal-entries.view` |
| GET | `/journal-entries/{journalEntry}/attachments` | Supporting documents of an entry | `attachments.view` |
| POST | `/journal-entries/{journalEntry}/attachments` | Attach a document (multipart/form-data) | `attachments.create` |
| GET | `/attachments/{attachment}/download` | Download a document | `attachments.view` |
| DELETE | `/attachments/{attachment}` | Remove a document of a draft | `attachments.delete` |
| GET | `/journal-entries` | List entries | `journal-entries.view` |
| POST | `/journal-entries` | Create (and optionally post) an entry | `journal-entries.create` |
| POST | `/journal-entries/simple` | Two-line entry by account codes | `journal-entries.create` |
| GET | `/journal-entries/{journalEntry}` | Show an entry with lines | `journal-entries.view` |
| PUT | `/journal-entries/{journalEntry}` | Update a draft (PATCH also accepted) | `journal-entries.update` |
| POST | `/journal-entries/{journalEntry}/post` | Post a draft | `journal-entries.post` |
| POST | `/journal-entries/{journalEntry}/reverse` | Reverse a posted entry | `journal-entries.reverse` |
| POST | `/journal-entries/{journalEntry}/submit` | Submit a draft for approval (maker) | `journal-entries.create` |
| POST | `/journal-entries/{journalEntry}/approve` | Approve and post (checker) | `journal-entries.approve` |
| POST | `/journal-entries/{journalEntry}/reject` | Reject back to the maker (checker) | `journal-entries.approve` |
| POST | `/journal-entries/{journalEntry}/void` | Void a draft | `journal-entries.void` |

### Chart of accounts

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/chart-of-accounts` | List accounts | `chart-of-accounts.view` |
| POST | `/chart-of-accounts` | Create an account | `chart-of-accounts.create` |
| GET | `/chart-of-accounts/export/{format}` | Export the chart (re-importable) | `chart-of-accounts.view` |
| GET | `/chart-of-accounts/import/template/{format}` | Import template with example rows | `chart-of-accounts.import` |
| POST | `/chart-of-accounts/import` | Import accounts from CSV or Excel (multipart/form-data) | `chart-of-accounts.import` |
| GET | `/chart-of-accounts/{chartOfAccount}/renumber-preview` | Preview a renumbering | `chart-of-accounts.restructure` |
| POST | `/chart-of-accounts/{chartOfAccount}/renumber` | Renumber an account (used accounts too) | `chart-of-accounts.restructure` |
| GET | `/chart-of-accounts/{chartOfAccount}/merge-preview` | Preview merging this account into another | `chart-of-accounts.restructure` |
| POST | `/chart-of-accounts/{chartOfAccount}/merge` | Merge this account into another | `chart-of-accounts.restructure` |
| GET | `/chart-templates` | Industry chart templates | `chart-templates.apply` |
| GET | `/chart-templates/{template}` | What a template would add to this company | `chart-templates.apply` |
| POST | `/chart-templates/{template}/apply` | Add a template's accounts to this company | `chart-templates.apply` |
| GET | `/chart-of-accounts/tree` | Whole chart as a nested tree | `chart-of-accounts.view` |
| GET | `/chart-of-accounts/{chartOfAccount}/balance` | Account balance | `chart-of-accounts.view` |
| GET | `/chart-of-accounts/{chartOfAccount}` | Show an account | `chart-of-accounts.view` |
| PUT | `/chart-of-accounts/{chartOfAccount}` | Update an account (PATCH also accepted) | `chart-of-accounts.update` |
| DELETE | `/chart-of-accounts/{chartOfAccount}` | Delete an account | `chart-of-accounts.delete` |
| GET | `/control-accounts` | Control accounts with balances and manual postings | `chart-of-accounts.view` |
| POST | `/control-accounts/recommended` | Mark the recommended control accounts | `control-accounts.manage` |
| GET | `/control-accounts/{chartOfAccount}/manual-postings` | Manual postings to a control account | `chart-of-accounts.view` |
| PUT | `/chart-of-accounts/{chartOfAccount}/control-type` | Set or clear an account's control type | `control-accounts.manage` |

### Reports

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/report-mapping` | Report lines and the mapping of every account | `report-mapping.manage` |
| POST | `/report-mapping/recommended` | Map the seeded accounts to their standard lines | `report-mapping.manage` |
| PUT | `/chart-of-accounts/{chartOfAccount}/report-mapping` | Map an account (and its sub-accounts) to a line | `report-mapping.manage` |
| POST | `/report-lines` | Add a statement line | `report-mapping.manage` |
| PUT | `/report-lines/{reportLine}` | Rename, move or reclassify a line | `report-mapping.manage` |
| DELETE | `/report-lines/{reportLine}` | Delete a custom line | `report-mapping.manage` |
| GET | `/reports/statements/{type}` | Financial statement by report lines | `reports.financial-statements.view` |
| GET | `/reports/{report}/export/{format}` | Download a report as CSV, Excel or a typeset PDF | `accounting.view` |
| POST | `/reports/{report}/exports/{format}` | Queue a background export | `accounting.view` |
| GET | `/exports` | Your background exports | `accounting.view` |
| GET | `/exports/{export}/download` | Download a finished export | `accounting.view` |
| DELETE | `/exports/{export}` | Delete one of your exports and its file | `accounting.view` |
| GET | `/reports/consolidated/{report}` | Consolidated report | `reports.consolidated.view` |
| GET | `/reports/trial-balance` | Trial balance | `reports.trial-balance.view` |
| GET | `/reports/balance-sheet` | Balance sheet | `reports.balance-sheet.view` |
| GET | `/reports/income-statement` | Income statement | `reports.income-statement.view` |
| GET | `/reports/general-ledger` | General ledger | `reports.general-ledger.view` |
| GET | `/reports/cash-flow` | Cash flow | `reports.cash-flow.view` |
| GET | `/reports/bank-book` | Bank book | `reports.bank-book.view` |
| GET | `/reports/cash-book` | Cash book | `reports.cash-book.view` |
| GET | `/reports/aged-receivables` | Aged receivables | `reports.aged-receivables.view` |
| GET | `/reports/aged-payables` | Aged payables | `reports.aged-payables.view` |
| GET | `/reports/account-statement` | Account statement | `reports.account-statement.view` |

### Periods

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| POST | `/periods/generate-monthly` | Create twelve monthly periods | `periods.create` |
| GET | `/periods` | List periods | `periods.view` |
| POST | `/periods` | Create periods | `periods.create` |
| GET | `/periods/{record}` | Show periods | `periods.view` |
| PUT | `/periods/{record}` | Update periods (PATCH also accepted) | `periods.update` |
| DELETE | `/periods/{record}` | Delete periods | `periods.delete` |
| GET | `/periods/{period}/close-checklist` | Pre-close checklist | `periods.view` |
| POST | `/periods/{period}/close` | Close a period | `periods.close` |
| POST | `/periods/{period}/reopen` | Reopen a closed period | `periods.reopen` |
| POST | `/periods/{period}/close-fiscal-year` | Year-end close | `periods.close` |
| GET | `/account-balance-snapshots` | List balance snapshots | `account-balance-snapshots.view` |
| GET | `/account-balance-snapshots/{record}` | Show a balance snapshot | `account-balance-snapshots.view` |

### Account types

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/account-types` | List account-types | `account-types.view` |
| POST | `/account-types` | Create account-types | `account-types.create` |
| GET | `/account-types/{record}` | Show account-types | `account-types.view` |
| PUT | `/account-types/{record}` | Update account-types (PATCH also accepted) | `account-types.update` |
| DELETE | `/account-types/{record}` | Delete account-types | `account-types.delete` |

### Currencies

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/currencies` | List currencies | `currencies.view` |
| POST | `/currencies` | Create currencies | `currencies.create` |
| GET | `/currencies/{record}` | Show currencies | `currencies.view` |
| PUT | `/currencies/{record}` | Update currencies (PATCH also accepted) | `currencies.update` |
| DELETE | `/currencies/{record}` | Delete currencies | `currencies.delete` |

### Cost centers

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/cost-centers` | List cost-centers | `cost-centers.view` |
| POST | `/cost-centers` | Create cost-centers | `cost-centers.create` |
| GET | `/cost-centers/{record}` | Show cost-centers | `cost-centers.view` |
| PUT | `/cost-centers/{record}` | Update cost-centers (PATCH also accepted) | `cost-centers.update` |
| DELETE | `/cost-centers/{record}` | Delete cost-centers | `cost-centers.delete` |

### Bank accounts

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/bank-accounts` | List bank-accounts | `bank-accounts.view` |
| POST | `/bank-accounts` | Create bank-accounts | `bank-accounts.create` |
| GET | `/bank-accounts/{record}` | Show bank-accounts | `bank-accounts.view` |
| PUT | `/bank-accounts/{record}` | Update bank-accounts (PATCH also accepted) | `bank-accounts.update` |
| DELETE | `/bank-accounts/{record}` | Delete bank-accounts | `bank-accounts.delete` |

### Reconciliations

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/reconciliations` | List reconciliations | `reconciliations.view` |
| POST | `/reconciliations` | Create reconciliations | `reconciliations.create` |
| GET | `/reconciliations/{record}` | Show reconciliations | `reconciliations.view` |
| PUT | `/reconciliations/{record}` | Update reconciliations (PATCH also accepted) | `reconciliations.update` |
| DELETE | `/reconciliations/{record}` | Delete reconciliations | `reconciliations.delete` |

### Tax

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| POST | `/tax/calculate` | Tax on an amount | `tax-codes.view` |
| POST | `/tax/entries` | Book a taxed document as a journal entry | `tax-entries.create` |
| GET | `/tax/returns/report` | The tax ledger of a period | `tax-returns.view` |
| GET | `/tax/returns/report/export/{format}` | Export the tax report | `tax-returns.view` |
| GET | `/tax/returns` | Filed tax returns | `tax-returns.view` |
| POST | `/tax/returns` | File a return for a period | `tax-returns.file` |
| GET | `/tax/returns/{taxReturn}` | A filed return | `tax-returns.view` |
| DELETE | `/tax/returns/{taxReturn}` | Void a return (its entry is reversed) | `tax-returns.file` |
| GET | `/tax-codes` | List tax-codes | `tax-codes.view` |
| POST | `/tax-codes` | Create tax-codes | `tax-codes.create` |
| GET | `/tax-codes/{record}` | Show tax-codes | `tax-codes.view` |
| PUT | `/tax-codes/{record}` | Update tax-codes (PATCH also accepted) | `tax-codes.update` |
| DELETE | `/tax-codes/{record}` | Delete tax-codes | `tax-codes.delete` |
| GET | `/tax-rates` | List tax-rates | `tax-rates.view` |
| POST | `/tax-rates` | Create tax-rates | `tax-rates.create` |
| GET | `/tax-rates/{record}` | Show tax-rates | `tax-rates.view` |
| PUT | `/tax-rates/{record}` | Update tax-rates (PATCH also accepted) | `tax-rates.update` |
| DELETE | `/tax-rates/{record}` | Delete tax-rates | `tax-rates.delete` |

### Recurring entries

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/recurring-entries` | Recurring entry templates | `recurring-entries.view` |
| POST | `/recurring-entries` | Create a template | `recurring-entries.create` |
| GET | `/recurring-entries/{recurringEntry}` | A template with its generated entries and upcoming dates | `recurring-entries.view` |
| PUT | `/recurring-entries/{recurringEntry}` | Change a template (PATCH also accepted) | `recurring-entries.update` |
| DELETE | `/recurring-entries/{recurringEntry}` | Delete a template that has not generated anything | `recurring-entries.delete` |
| POST | `/recurring-entries/{recurringEntry}/pause` | Pause a template | `recurring-entries.update` |
| POST | `/recurring-entries/{recurringEntry}/resume` | Resume a paused template | `recurring-entries.update` |
| POST | `/recurring-entries/{recurringEntry}/run` | Generate the next entry now | `recurring-entries.run` |

### Voucher types

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/voucher-types` | List voucher types with their next numbers | `voucher-types.view` |
| POST | `/voucher-types` | Create a voucher type | `voucher-types.create` |
| GET | `/voucher-types/{record}` | Show a voucher type | `voucher-types.view` |
| PUT | `/voucher-types/{record}` | Update a voucher type (PATCH also accepted) | `voucher-types.update` |
| DELETE | `/voucher-types/{record}` | Delete an unused voucher type | `voucher-types.delete` |
| GET | `/voucher-types/{record}/next-number` | Preview the next number | `voucher-types.view` |

### Companies

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/companies` | Companies you can work in | `accounting.view` |
| POST | `/companies` | Create a company | `companies.manage` |
| GET | `/companies/{company}` | Show a company with its members | `companies.manage` |
| PUT | `/companies/{company}` | Update a company | `companies.manage` |
| POST | `/companies/{company}/users` | Give a user access | `companies.manage` |
| DELETE | `/companies/{company}/users/{user}` | Remove a user's access | `companies.manage` |

### Users & roles

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/users` | List users | `user.view` |
| POST | `/users` | Create a user | `user.create` |
| GET | `/users/{user}` | Show a user with all effective permissions | `user.view` |
| PUT | `/users/{user}` | Update a user (PATCH also accepted) | `user.update` |
| DELETE | `/users/{user}` | Delete a user | `user.delete` |
| PUT | `/users/{user}/roles` | Replace a user's roles | `user.assign-role` |
| PUT | `/users/{user}/permissions` | Replace a user's direct permissions | `user.assign-permission` |
| GET | `/roles` | List roles with permission and user counts | `accounting.manage-settings` |
| POST | `/roles` | Create a role | `accounting.manage-settings` |
| GET | `/roles/{role}` | Show a role | `accounting.manage-settings` |
| PUT | `/roles/{role}` | Update a role (PATCH also accepted) | `accounting.manage-settings` |
| DELETE | `/roles/{role}` | Delete a role | `accounting.manage-settings` |
| GET | `/permissions` | All permissions grouped by area | `accounting.manage-settings` |

### System

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/dashboard` | Dashboard overview | `accounting.view` |
| GET | `/health` | Installation health | `accounting.view` |

### Bank statements

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/bank-statements` | Imported statements | `bank-statements.view` |
| POST | `/bank-statements/import` | Import a statement (CSV or XLSX) | `bank-statements.import` |
| GET | `/bank-statements/{bankStatement}` | A statement with its transactions | `bank-statements.view` |
| PUT | `/bank-statements/{bankStatement}` | Set the closing balance (PATCH also accepted) | `bank-statements.match` |
| DELETE | `/bank-statements/{bankStatement}` | Delete a statement and release its matches | `bank-statements.match` |
| POST | `/bank-statements/{bankStatement}/auto-match` | Match every transaction that has exactly one ledger candidate | `bank-statements.match` |
| POST | `/bank-statements/{bankStatement}/reconcile` | Reconcile the statement | `bank-statements.match` |
| GET | `/bank-statement-lines/{bankStatementLine}/candidates` | Ledger lines that could be this transaction | `bank-statements.view` |
| POST | `/bank-statement-lines/{bankStatementLine}/match` | Match a transaction to a ledger line | `bank-statements.match` |
| POST | `/bank-statement-lines/{bankStatementLine}/unmatch` | Release a matched or booked transaction | `bank-statements.match` |
| POST | `/bank-statement-lines/{bankStatementLine}/ignore` | Ignore (or restore) a transaction | `bank-statements.match` |
| POST | `/bank-statement-lines/{bankStatementLine}/create-entry` | Book a transaction as a journal entry | `bank-statements.match` |

### Budgets

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/budgets` | Budgets | `budgets.view` |
| POST | `/budgets` | Create a draft budget | `budgets.create` |
| GET | `/budgets/{budget}` | Budget against actual | `budgets.view` |
| PUT | `/budgets/{budget}` | Change a draft budget (PATCH also accepted) | `budgets.update` |
| DELETE | `/budgets/{budget}` | Delete a budget that is not approved | `budgets.delete` |
| POST | `/budgets/{budget}/approve` | Approve a draft budget | `budgets.approve` |
| POST | `/budgets/{budget}/reopen` | Reopen an approved or closed budget as a draft | `budgets.update` |
| POST | `/budgets/{budget}/close` | Close an approved budget | `budgets.approve` |
| POST | `/budgets/{budget}/copy` | Copy as a draft, optionally shifted and raised | `budgets.create` |
| GET | `/budgets/{budget}/export/{format}` | Export budget against actual | `budgets.view` |

### Currency revaluation

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/fx-revaluation` | Past revaluations and the dated exchange rates | `fx-revaluation.view` |
| GET | `/fx-revaluation/preview` | What a revaluation would adjust (nothing is posted) | `fx-revaluation.view` |
| POST | `/fx-revaluation` | Post a revaluation | `fx-revaluation.run` |
| POST | `/fx-revaluation/rates` | Save a dated exchange rate (replaces the rate of that currency and date) | `fx-revaluation.rates` |
| DELETE | `/fx-revaluation/rates/{exchangeRate}` | Remove a dated rate | `fx-revaluation.rates` |
| GET | `/fx-revaluation/{fxRevaluation}` | One revaluation with its accounts | `fx-revaluation.view` |
| POST | `/fx-revaluation/{fxRevaluation}/reverse` | Reverse a revaluation | `fx-revaluation.run` |

### FBR

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/fbr` | Posted sales invoices and credit notes with their FBR status | `fbr.view` |
| POST | `/fbr/documents/{document}/submit` | Send a sales invoice or credit note to FBR (or retry a failed one) | `fbr.submit` |

### Fixed assets

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/fixed-assets` | Fixed asset register at a date | `fixed-assets.view` |
| POST | `/fixed-assets` | Register an asset | `fixed-assets.create` |
| GET | `/fixed-assets/depreciation` | Preview the depreciation due | `fixed-assets.view` |
| POST | `/fixed-assets/depreciation` | Book the depreciation due | `fixed-assets.depreciate` |
| GET | `/fixed-assets/export/{format}` | Export the register | `fixed-assets.view` |
| GET | `/fixed-assets/{asset}` | An asset with its depreciation history | `fixed-assets.view` |
| PUT | `/fixed-assets/{asset}` | Change an asset (PATCH also accepted) | `fixed-assets.update` |
| DELETE | `/fixed-assets/{asset}` | Delete an asset without entries | `fixed-assets.delete` |
| POST | `/fixed-assets/{asset}/dispose` | Sell or scrap an asset | `fixed-assets.dispose` |

### Inventory

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/inventory` | Stock valuation at a date | `inventory.view` |
| GET | `/inventory/export/{format}` | Export the stock valuation | `inventory.view` |
| POST | `/inventory/items` | Create an item | `inventory.manage` |
| GET | `/inventory/items/{item}` | An item with its stock card | `inventory.view` |
| PUT | `/inventory/items/{item}` | Change an item (PATCH also accepted) | `inventory.manage` |
| DELETE | `/inventory/items/{item}` | Delete an item without movements | `inventory.manage` |
| GET | `/inventory/warehouses` | Warehouses | `inventory.view` |
| POST | `/inventory/warehouses` | Add a warehouse | `inventory.manage` |
| PUT | `/inventory/warehouses/{warehouse}` | Change a warehouse (PATCH also accepted) | `inventory.manage` |
| DELETE | `/inventory/warehouses/{warehouse}` | Delete a warehouse without movements | `inventory.manage` |
| GET | `/inventory/movements` | The latest 200 stock movements | `inventory.view` |
| POST | `/inventory/movements` | Receive, issue, adjust or transfer stock | `inventory.move` |

### Payroll

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/payroll` | Payroll runs, newest first | `payroll.view` |
| POST | `/payroll/runs` | Start a payroll run for a month | `payroll.run` |
| GET | `/payroll/runs/{run}` | A run with its payslips and their lines | `payroll.view` |
| POST | `/payroll/runs/{run}/recalculate` | Work the payslips out again (draft runs) | `payroll.run` |
| POST | `/payroll/runs/{run}/post` | Post the run to the books | `payroll.post` |
| POST | `/payroll/runs/{run}/pay` | Pay the net salaries | `payroll.post` |
| POST | `/payroll/runs/{run}/void` | Void a posted or paid run | `payroll.void` |
| DELETE | `/payroll/runs/{run}` | Delete a draft run | `payroll.run` |
| GET | `/payroll/runs/{run}/payslips/{payslip}` | One payslip | `payroll.view` |
| GET | `/payroll/employees` | Employees | `payroll.view` |
| POST | `/payroll/employees` | Add an employee | `payroll.manage` |
| GET | `/payroll/employees/{employee}` | An employee with their allowances and deductions | `payroll.view` |
| PUT | `/payroll/employees/{employee}` | Change an employee (PATCH also accepted) | `payroll.manage` |
| DELETE | `/payroll/employees/{employee}` | Delete an employee without payslips | `payroll.manage` |
| GET | `/payroll/components` | Allowances and deductions | `payroll.view` |
| POST | `/payroll/components` | Add an allowance or deduction | `payroll.manage` |
| PUT | `/payroll/components/{component}` | Change a component (PATCH also accepted) | `payroll.manage` |
| DELETE | `/payroll/components/{component}` | Delete a component that is not in use | `payroll.manage` |
| GET | `/payroll/grades` | Salary grades | `payroll.view` |
| POST | `/payroll/grades` | Add a salary grade | `payroll.manage` |
| GET | `/payroll/grades/{grade}` | A grade with its allowances | `payroll.view` |
| PUT | `/payroll/grades/{grade}` | Change a grade (PATCH also accepted) | `payroll.manage` |
| DELETE | `/payroll/grades/{grade}` | Delete a grade nobody is on | `payroll.manage` |
| POST | `/payroll/grades/{grade}/assign` | Put a group of employees on the grade | `payroll.manage` |
| POST | `/payroll/bulk/components` | Give a component to a group of employees, or take it away | `payroll.manage` |
| GET | `/payroll/employees/{employee}/revisions` | The salary history of an employee | `payroll.view` |
| POST | `/payroll/employees/{employee}/revisions` | Revise an employee's salary from a date | `payroll.manage` |
| POST | `/payroll/revisions/preview` | Preview a raise for a group (nothing is saved) | `payroll.manage` |
| POST | `/payroll/revisions` | Apply a raise to a group | `payroll.manage` |
| GET | `/payroll/arrears` | The arrears register with totals | `payroll.view` |
| POST | `/payroll/arrears/preview` | Work arrears out without saving | `payroll.run` |
| POST | `/payroll/arrears` | Create arrears (drafts) from the posted payslips | `payroll.run` |
| POST | `/payroll/arrears/approve-all` | Approve every arrears draft | `payroll.post` |
| GET | `/payroll/arrears/{arrear}` | One arrears record with its months | `payroll.view` |
| POST | `/payroll/arrears/{arrear}/approve` | Approve arrears | `payroll.post` |
| POST | `/payroll/arrears/{arrear}/cancel` | Cancel arrears not yet in a run | `payroll.post` |
| GET | `/payroll/attendance` | The attendance sheet of a month | `payroll.view` |
| POST | `/payroll/attendance` | Save the attendance sheet of a month | `payroll.manage` |
| GET | `/payroll/leave-types` | Leave types | `payroll.view` |
| POST | `/payroll/leave-types` | Add a leave type | `payroll.manage` |
| PUT | `/payroll/leave-types/{leaveType}` | Change a leave type (PATCH also accepted) | `payroll.manage` |
| DELETE | `/payroll/leave-types/{leaveType}` | Delete a leave type nobody took | `payroll.manage` |
| GET | `/payroll/leaves` | Leaves, newest first | `payroll.view` |
| GET | `/payroll/leaves/balances` | Leave balances of a year | `payroll.view` |
| POST | `/payroll/leaves` | Record leave | `payroll.manage` |
| POST | `/payroll/leaves/{leave}/cancel` | Cancel a leave | `payroll.manage` |
| GET | `/payroll/loans` | Loans and advances | `payroll.view` |
| POST | `/payroll/loans` | Record a loan or advance with its instalment schedule | `payroll.manage` |
| GET | `/payroll/loans/{loan}` | A loan with its schedule | `payroll.view` |
| POST | `/payroll/loans/{loan}/disburse` | Pay the loan out | `payroll.post` |
| POST | `/payroll/loans/{loan}/settle` | The employee pays back what is left in cash | `payroll.post` |
| POST | `/payroll/loans/{loan}/skip` | Move the next instalment to the end of the schedule | `payroll.manage` |
| POST | `/payroll/loans/{loan}/cancel` | Cancel a loan not yet paid out | `payroll.manage` |
| GET | `/payroll/schemes` | Contribution schemes (EOBI, PESSI/SESSI, provident fund) | `payroll.view` |
| POST | `/payroll/schemes` | Add a contribution scheme | `payroll.manage` |
| GET | `/payroll/schemes/{scheme}` | A scheme | `payroll.view` |
| PUT | `/payroll/schemes/{scheme}` | Change a scheme (PATCH also accepted) | `payroll.manage` |
| DELETE | `/payroll/schemes/{scheme}` | Delete a scheme | `payroll.manage` |
| POST | `/payroll/schemes/{scheme}/assign` | Give a scheme to employees or take it away | `payroll.manage` |
| GET | `/payroll/runs/{run}/bank-file/{format}` | The bank salary file of a posted run | `payroll.post` |
| GET | `/payroll/runs/{run}/payslips/{payslip}/print` | A payslip as a printable page | `payroll.view` |
| GET | `/payroll/runs/{run}/payslips/{payslip}/pdf` | A payslip as a PDF | `payroll.view` |
| POST | `/payroll/runs/{run}/payslips/{payslip}/email` | E-mail one payslip to the employee | `payroll.manage` |
| POST | `/payroll/runs/{run}/email-payslips` | E-mail every payslip of a posted run | `payroll.manage` |
| POST | `/payroll/runs/{run}/submit` | HR submits a draft run to finance | `payroll.run` |
| POST | `/payroll/runs/{run}/withdraw` | Take a submitted or approved run back to draft | `payroll.run` |
| POST | `/payroll/runs/{run}/approve` | Finance approves a submitted run | `payroll.approve` |
| POST | `/payroll/runs/{run}/reject` | Finance sends a submitted run back to HR | `payroll.approve` |
| GET | `/payroll/adjustments` | Bonuses and other one-off pay of a month | `payroll.view` |
| POST | `/payroll/adjustments` | Add a bonus, extra allowance or fine for one employee and month | `payroll.manage` |
| POST | `/payroll/adjustments/bulk` | The same bonus for every active employee (or the ones named) | `payroll.manage` |
| POST | `/payroll/adjustments/{adjustment}/cancel` | Cancel an adjustment no run has taken up | `payroll.manage` |
| GET | `/payroll/settlements` | Final settlements | `payroll.view` |
| POST | `/payroll/settlements/preview` | Work a settlement out without saving | `payroll.manage` |
| POST | `/payroll/settlements` | Save a settlement as a draft | `payroll.manage` |
| GET | `/payroll/settlements/{settlement}` | One settlement | `payroll.view` |
| DELETE | `/payroll/settlements/{settlement}` | Delete a draft settlement | `payroll.manage` |
| POST | `/payroll/settlements/{settlement}/post` | Post a settlement to the books | `payroll.post` |
| POST | `/payroll/settlements/{settlement}/pay` | Pay a posted settlement | `payroll.post` |
| POST | `/payroll/settlements/{settlement}/void` | Void a settlement | `payroll.void` |
| GET | `/payroll/reports` | Month comparison, cost centers and headcount | `payroll.view` |
| GET | `/payroll/reports/{report}/export/{format}` | Download a payroll report | `payroll.view` |
| GET | `/payroll/tax` | Salary and tax withheld per employee for a tax year | `payroll.view` |
| GET | `/payroll/tax/annual/{format}` | Download the annual salary tax statement | `payroll.view` |
| GET | `/payroll/tax/certificate/{employee}` | An employee's salary tax certificate | `payroll.view` |

### Receivables & payables

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/parties` | Customers and suppliers | `parties.view` |
| POST | `/parties` | Create a customer or supplier | `parties.create` |
| GET | `/parties/{party}` | A party with its balance and open items | `parties.view` |
| PUT | `/parties/{party}` | Change a party (PATCH also accepted) | `parties.update` |
| DELETE | `/parties/{party}` | Delete a party without documents or payments | `parties.delete` |
| GET | `/parties/{party}/statement` | Statement of account with a running balance | `parties.view` |
| GET | `/party-documents` | Invoices, bills, credit and debit notes | `party-documents.view` |
| POST | `/party-documents` | Create a draft document | `party-documents.create` |
| GET | `/party-documents/{partyDocument}` | A document with its lines | `party-documents.view` |
| PUT | `/party-documents/{partyDocument}` | Change a draft (PATCH also accepted) | `party-documents.update` |
| DELETE | `/party-documents/{partyDocument}` | Delete a draft | `party-documents.delete` |
| POST | `/party-documents/{partyDocument}/post` | Number and post a draft | `party-documents.post` |
| POST | `/party-documents/{partyDocument}/void` | Void a posted document (its entry is reversed) | `party-documents.void` |
| POST | `/party-documents/{partyDocument}/apply` | Apply a credit or debit note to invoices or bills | `party-documents.post` |
| GET | `/party-payments` | Receipts and payments | `party-payments.view` |
| POST | `/party-payments` | Record a receipt from a customer or a payment to a supplier | `party-payments.create` |
| GET | `/party-payments/{partyPayment}` | A payment with its allocations | `party-payments.view` |
| POST | `/party-payments/{partyPayment}/allocate` | Allocate (more of) a payment to documents | `party-payments.create` |
| POST | `/party-payments/{partyPayment}/void` | Void a payment (entry reversed, allocations released) | `party-payments.void` |
| DELETE | `/party-allocations/{partyAllocation}` | Undo an allocation | `party-payments.create` |
| GET | `/receivables/aging` | Ageing by customer or supplier, checked against the control account | `party-documents.view` |
| GET | `/receivables/aging/export/{format}` | Export the ageing | `party-documents.view` |

### Settings

| Method | Endpoint | What it does | Permission |
|--------|----------|--------------|------------|
| GET | `/features` | Every optional module and payroll feature with its switch | `accounting.manage-settings` |
| PUT | `/features` | Switch features on or off | `accounting.manage-settings` |
