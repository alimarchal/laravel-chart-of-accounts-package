# Changelog

All notable changes to `laravel-chart-of-accounts` will be documented in this file.

## [2.31.0] - 2026-11-02

Payroll output release.

### Added
- **Payslip files:** a printable page and a PDF for every payslip; e-mail one payslip or every payslip of a posted run (queued when
  `accounting.payroll.payslip_mail.queue` is on). Employees now have an e-mail address.
- **Automatic payroll run:** `php artisan accounting:payroll-run` creates the draft run of a month (default: the previous one) for one company or all;
  scheduled monthly when `accounting.payroll.auto_run.enabled` is on (day and time configurable).
- **Final settlement:** gratuity (days per year x basic/30 x years served, after a minimum service), pay for unused leave, a signed adjustment and the
  loans the employee still owes, worked out in a preview, saved as a draft, posted, paid and voidable (a void gives the loans back).
  Config `accounting.payroll.gratuity`.
- **Tax:** the annual salary tax statement for every employee (CSV, Excel, PDF) and a printable tax certificate per employee; the tax year starts
  in `accounting.payroll.tax_year_start_month` (default July).
- **Payroll reports:** month-by-month comparison with change, cost by cost center, and headcount (joined, left, on the payroll), each as CSV, Excel or PDF.
- React and Blade screens (settlements, reports, tax, tax certificate; employee, run and payslip screens updated) and API endpoints under
  `/payroll/settlements`, `/payroll/reports`, `/payroll/tax` and `/payroll/runs/{id}/payslips/{id}/(print|pdf|email)`.

## [2.30.0] - 2026-11-01

Payroll operations release.

### Added
- **Attendance sheet and leave:** absent days and unpaid leave are taken off the salary by the day; leave types (paid/unpaid, yearly entitlement),
  leaves with overlap and balance checks, half days, yearly balances.
- **Overtime** for flagged employees (hourly rate x multiplier, config `accounting.payroll.overtime`).
- **Loans and salary advances:** schedule, payout entry, recovery from salary by the payroll run, skip, cash settlement, closing.
  Config `accounting.payroll.employee_loans_account`.
- **Contribution schemes** (EOBI, PESSI/SESSI, provident fund): employee share deducted, employer share booked as a cost owed to the fund,
  optional on arrears; employer contributions shown on payslips and totalled on runs.
- **Bank salary file** (CSV/Excel) with configurable layouts and a list of employees without a bank account.
- React and Blade screens (attendance, leave, loans, contributions; employee, run and payslip screens updated) and API endpoints under
  `/payroll/attendance`, `/payroll/leave-types`, `/payroll/leaves`, `/payroll/loans`, `/payroll/schemes` and `/payroll/runs/{id}/bank-file/{format}`.

### Fixed
- Adding a fixed or percent pay component from the React or Blade screen failed (the screens send a blank rate, which reached the database as
  null; 2.29.0 only). Blank numbers on pay components, leave types and contribution schemes now mean none.

## [2.29.0] - 2026-10-31

Payroll pay structure release.

### Added
- **Quantity x rate pay components** (fuel litres at today's price): the rate is kept on the component, the quantity per employee.
- **Salary grades:** a basic salary with its allowances and deductions; employees on a grade follow it, with their own components overriding.
- **Bulk changes:** give or take away a component for many employees, move a group to a grade, raise salaries by percent, by an amount or to
  an amount (with rounding and a preview).
- **Salary history:** every change of salary is recorded with its effective date and reason; a change in the middle of a month is paid by the day.
- **Arrears:** back pay of a late raise worked out from the posted payslips, reviewed, approved, and paid with the payroll run as its own line
  with its own tax (as if paid in the months it belongs to) and a register. New config `accounting.payroll.arrears_account`.
- React and Blade screens (grades, bulk changes, arrears; component, employee and payslip screens updated) and API endpoints under
  `/payroll/grades`, `/payroll/bulk/components`, `/payroll/revisions`, `/payroll/employees/{id}/revisions` and `/payroll/arrears`.

### Fixed
- The payslip screens now list the Arrears line among the earnings; a TypeScript type of the receipt allocations was completed.

## [2.28.0] - 2026-10-30

Security review release.

### Security
- Excel (.xlsx) imports reject workbooks that declare a DOCTYPE or entities and parts larger than 50 MB uncompressed (zip bomb).
- CSV exports prefix cells that start with `=`, `+`, `-` or `@` so spreadsheets do not run them as formulas.
- The FBR gateway refuses a non-`https://` address, so the token is never sent in clear text.

### Added
- Tests: every route has an ability check and GETs never write; no API resource serves, changes or deletes another company's record by id; attachment whitelist.
- `docs/uat.md`: user acceptance checklist for accountants; README Security section.

## [2.27.0] - 2026-10-29

Urdu release.

### Added
- **Urdu (اردو) and right-to-left screens** for the React and Blade UIs: a language switcher, `?lang=` and `ACCOUNTING_LOCALE`, a
  dictionary of about 500 phrases (`resources/lang/ur.json`) covering module names, buttons, statuses, table headings and
  reports, phrase-by-phrase translation (so any screen follows without being rewritten), `lang`/`dir` on the page, a right-to-left stylesheet
  (alignment, spacing, an Urdu font stack, the starter kit's sidebar on the right). Applications can add or change phrases in their
  own `lang/ur.json`, and add more languages through `accounting.locales`.
- The API, PDFs and exports are not translated. The Urdu wording has not been reviewed by an accountant.

## [2.26.0] - 2026-10-28

Performance release.

### Changed
- **Receivables and payables ageing, the ledger-against-sub-ledger check and the dashboard** read their data for all customers at
  once instead of several queries per customer: 1,000 customers and 10,000 invoices went from 7.7 s (3,001 queries) to 0.28 s
  (4 queries); the dashboard from 18 s to 0.4 s.
- **Stock valuation** no longer does work proportional to items × movements: 2,000 items went from 11.6 s to 0.22 s.
- **Depreciation planning** reads what is booked on all assets in one query; **bank statement auto-match** reads the ledger lines
  that can match once per bank account and matches in memory: 1,500 lines went from 116 s to 12 s; payroll payslip lines are
  inserted in batches.

### Added
- Indexes on the party allocation, payslip, payslip line and stock movement lookups (migration `add_scale_indexes`).
- `tests/Performance/ScaleTest.php`: query-count guards in the normal test run, and a benchmark (`ACCOUNTING_PERF=1`) that prints
  timings on any database; results in `docs/performance.md`.

## [2.25.0] - 2026-10-27

FBR invoicing release.

### Added
- **FBR digital invoices**: posted sales invoices and credit notes are turned into the digital-invoice payload and sent through a
  gateway; the invoice number FBR returns is kept, an accepted invoice is never sent twice, a refused one keeps the reason and can
  be retried, and every attempt stores what was sent and received.
- Gateways: `fake` (accepts locally, the default), `live` (JSON with a bearer token to a configured URL) and your own
  `FbrGateway`; optional `auto_submit` on a queue when an invoice is posted. Not yet exercised against FBR's own service: test
  in their sandbox before relying on it.
- FBR screen (React and Blade), API (`/fbr`, `/fbr/documents/{id}/submit`), permissions `fbr.view/submit`, config
  `accounting.fbr`, OpenAPI and Postman.

## [2.24.0] - 2026-10-26

Payroll release.

### Added
- **Employees, allowances and deductions** and a **monthly payroll run**: payslips with basic pay, earnings, deductions and income
  tax withheld from configurable slabs, proration for joiners and leavers, negative net pay refused, one run per month.
- **Posting, payment and void**: one salary entry in the payroll module (expense by account and cost center, liabilities for
  deductions and tax, net pay owed), payment against a bank or cash account, void by reversing both entries.
- Printable payslips; React and Blade screens (runs, run, payslip, employees, employee form, components), API (`/payroll/...`),
  permissions `payroll.view/manage/run/post/void` (the accountant prepares, the approver posts and pays), config
  `accounting.payroll`, OpenAPI and Postman.

## [2.23.0] - 2026-10-25

Inventory release.

### Added
- **Items, warehouses and a stock ledger** valued at moving average cost: receipts (debit inventory), issues (debit cost of goods
  sold at the average, last unit takes the remaining value), count adjustments and transfers between warehouses; no negative stock
  in total or per warehouse; every movement and its journal entry in one transaction.
- **Stock valuation** at any date with per-warehouse quantities, reorder-level flags, a ledger-against-stock check and
  CSV/Excel/PDF export; **stock card** per item with the running quantity and value.
- React and Blade screens (valuation, item, item form, warehouses, movements, move-stock form), API (`/inventory`,
  `/inventory/items`, `/inventory/warehouses`, `/inventory/movements`), permissions `inventory.view/manage/move`, OpenAPI and Postman.

### Fixed
- Decimal amounts are accepted by the browser in the fixed asset form and the disposal proceeds field.

## [2.22.0] - 2026-10-24

Fixed assets release.

### Added
- **Fixed asset register**: cost, salvage value, useful life, straight-line or declining-balance method, the cost / accumulated
  depreciation / expense accounts, optional booking of the purchase, edit locks once entries exist, register report with a
  ledger-against-register check and CSV/Excel/PDF export.
- **Depreciation run**: preview and book what is due month by month (one entry per month, idempotent, catches up missed months,
  never below salvage value, exact to the cent).
- **Disposal**: sell or scrap with the gain or loss booked, depreciation first brought up to date.
- React and Blade screens (register, asset, form, depreciation), API (`/fixed-assets`, `/fixed-assets/depreciation`,
  `/fixed-assets/{id}/dispose`), permissions `fixed-assets.*`, OpenAPI and Postman.

## [2.21.0] - 2026-10-23

Accounting dashboard release.

### Added
- **Overview page** (`/accounting/overview`, React and Blade) and `GET /dashboard` API: cash and bank balances, receivable and
  payable totals with overdue amounts and ageing bars, income and expense for this month, last month and the year so far, a
  monthly trend chart (closing entries left out, table view available), top expenses, recent journal entries, and a
  "needs attention" list (entries awaiting approval, drafts, overdue invoices, unmatched bank lines, accounts over or near budget,
  tax not yet filed) with links to the page that deals with each.
- Each section is shown only to users holding the permission for it (`reports.income-statement.view`, `reports.balance-sheet.view`,
  `parties.view`, `journal-entries.view`, `bank-statements.view`, `budgets.view`, `tax-returns.view`); the rest is `null` in the API.
- `DashboardService::overview($user, $asOf, $months)` for use in your own pages.

## [2.20.0] - 2026-10-22

Receivables and payables release.

### Added
- **Customers and suppliers** (parties) with payment terms, credit limit, tax number and optional own receivable / payable account.
- **Invoices, bills, credit notes and debit notes**: lines with tax codes (exclusive or inclusive prices), drafts, gapless numbers
  per kind and year assigned at posting, one journal entry per document in the control account's module (tax lines marked for the
  tax report), void by reversal.
- **Receipts and payments** with allocation to invoices and bills (specific amounts or oldest due first), credit and debit notes
  applied to documents, unapplied amounts, undo of allocations.
- **Open items, statements of account and ageing** by customer / supplier (days past due buckets, unapplied), CSV / XLSX / PDF
  export, and a **check of the sub-ledger against the control account**.
- Screens (React and Blade) and API for all of it: `/parties`, `/party-documents`, `/party-payments`, `/party-allocations`,
  `/receivables/aging`.
- Permissions `parties.*`, `party-documents.*`, `party-payments.*` (accountant all; approver, auditor, viewer view); audited
  (`PARTY_*`).

## [2.19.0] - 2026-10-21

Tax engine release.

### Added
- **Tax codes** have a kind (output / input / withheld / advance), the account the tax is booked to and a jurisdiction;
  rates are picked by date.
- **Tax calculation** (tax-exclusive or inclusive, exact to the cent) and **taxed journal lines**: a line with a
  `tax_code_id` is split into its taxable amount and a tax line on the code's tax account; both are marked
  (`tax_role`) so reports never depend on the screen the entry came from.
- **Taxed documents**: invoice, bill, credit / debit note, payment or receipt with tax withheld, in React, Blade and the API.
- **Tax report** by tax code and period (taxable base, tax, documents, net payable, withheld, advance) with the documents
  behind it and CSV / XLSX / PDF export, and **tax returns**: file a period (output offset against input in one entry,
  the difference on a payable account), no overlapping periods, void by reversal.
- Permissions `tax-returns.view` (accountant, approver, auditor, viewer), `tax-returns.file` and `tax-entries.create`
  (accountant); audited (`TAX_RETURN_*`).

### Changed
- `accounting_journal_entry_lines` gains `tax_code_id`, `tax_role` and `tax_rate`; `accounting_tax_codes` gains `kind`,
  `tax_account_id` and `jurisdiction` (existing codes become output codes without an account). The journal entry form
  keeps the tax markers of a draft it edits.

## [2.18.0] - 2026-10-20

Budgets release.

### Added
- **Budgets**: income and expense plans by account (and cost center) and month, up to 24 months; an annual figure
  spread evenly or amounts by month; build from last year's actuals with a percentage; copy to a later year.
  Draft → approved (`budgets.approve`) → closed, reopen to revise, one approved budget per month.
- **Budget against actual** for any range and cost center: budget, actual, variance (favourable when positive), share
  used, status (ok / warning / over / behind / unbudgeted), months per account, totals; CSV / XLSX / PDF export.
- **Optional posting control** (`ACCOUNTING_BUDGET_CONTROL=block`): refuses an entry that takes a budgeted expense
  account past its cumulative budget; `budgets.override` may still post; `ACCOUNTING_BUDGET_WARN_PERCENT` sets the warning level.
- Screens (React and Blade): list, form, report with filters and monthly detail; API `/budgets` (+ `/approve`, `/close`,
  `/reopen`, `/copy`, `/export/{format}`).
- Permissions `budgets.view` (accountant, approver, auditor, viewer), `.create / .update / .delete` (accountant),
  `.approve` (approver), `.override`; audited (`BUDGET_*`).

## [2.17.0] - 2026-10-19

Bank statement import release.

### Added
- **Bank statement import** (CSV / XLSX): columns recognised by name (deposit / withdrawal columns or one signed
  amount, optional balance), many date and amount formats, a preview, and no duplicates — every transaction has a unique
  fingerprint, so an overlapping statement imports only what is new.
- **Matching**: transactions match posted ledger lines of the bank's account (same amount, nearby date,
  `ACCOUNTING_BANK_MATCH_DAYS`); auto-match for the unambiguous ones, manual match from candidates, unmatch, ignore.
  Matched ledger lines are marked cleared.
- **Book a transaction** as a journal entry against a chosen account (posted when possible, else a draft that stays
  linked), and **reconcile** a statement into a bank reconciliation. Reconciled statements are locked.
- Screens (React and Blade): statements, import with preview, statement with match / book / ignore; API
  `/bank-statements` (+ `/import`, `/auto-match`, `/reconcile`) and `/bank-statement-lines/{id}/…`.
- Permissions `bank-statements.view` (accountant, approver, auditor, viewer), `.import` and `.match` (accountant);
  audited (`BANK_STATEMENT_*`).

## [2.16.0] - 2026-10-18

Currency revaluation release.

### Added
- **Foreign-currency revaluation**: restates asset and liability accounts denominated in a foreign currency to
  balance × closing rate at a date, with one adjusting entry in the base currency and the net unrealised gain or loss
  booked to a chosen income/expense account. Preview before posting, per-account detail kept, repeatable (only the
  difference is adjusted), optional auto-reverse into the next period, reverse later, audited (`FX_REVALUATION_*`).
- **Dated exchange rates** (`accounting_exchange_rates`): the revaluation uses the latest rate on or before its date,
  else the currency's own rate.
- Screens (React and Blade): revaluation with preview, rate history, history of runs, details; API
  `/fx-revaluation` (+ `/preview`, `/{id}`, `/{id}/reverse`, `/rates`); `accounting.fx.gain_loss_account` /
  `ACCOUNTING_FX_GAIN_LOSS_ACCOUNT` pre-selects the gain/loss account.
- Permissions `fx-revaluation.view` (accountant, approver, auditor, viewer), `fx-revaluation.run`,
  `fx-revaluation.rates` (accountant).
- Revaluation entries may adjust a foreign-currency account in the base currency (origin `fx-revaluation` only);
  manual base-currency entries on such accounts are still rejected.

## [2.15.0] - 2026-10-17

Recurring entries release.

### Added
- **Recurring entries**: journal entry templates (balanced lines, voucher type, narration) that repeat daily, weekly,
  monthly, quarterly or yearly, every N periods, until an end date or after N entries. Month-end dates are clamped in
  short months and return to the 31st.
- **Generation** (`accounting:run-recurring`, scheduled daily at `ACCOUNTING_RECURRING_TIME` unless
  `ACCOUNTING_RECURRING_SCHEDULE=false`): a draft, or posted as the template's creator; under maker-checker it is
  submitted for approval; a closed period, a control account or a creator who may not post leave a draft with the
  reason. One run per scheduled date (unique), retries of a failed creation, catch-up of missed occurrences up to
  `ACCOUNTING_RECURRING_MAX_CATCH_UP`, pause / resume (skipping or generating what was missed), generate now.
- Screens (React and Blade): list, form with a live balance check, details with upcoming dates and the history of
  generated entries; API `/recurring-entries` (+ `/pause`, `/resume`, `/run`).
- Permissions `recurring-entries.view` (accountant, approver, auditor, viewer) and `.create / .update / .delete / .run`
  (accountant); audited (`RECURRING_ENTRY_*`).
- Works with applications that use immutable dates (`Date::use(CarbonImmutable::class)`, the starter kits' default).

## [2.14.0] - 2026-10-16

Industry chart templates release.

### Added
- **Chart templates**: trading, manufacturing, services, school, NGO and healthcare (and the general chart): a base
  chart plus the accounts the industry adds, including a Manufacturing Overheads group, contra income and purchase
  accounts for trading, fund accounts for NGOs and patient / insurance receivables for healthcare.
- **Preview and add** from Chart of Accounts → Templates (React and Blade), `GET /chart-templates[/{key}]` and
  `POST /chart-templates/{key}/apply` (`dry_run`), or `accounting:chart-templates [template] [--dry-run] [--company=]`.
  Only missing accounts are added, parents first; existing accounts are never changed, so a template can be applied to
  a chart in use and repeated. Every new account is mapped to its statement line. Audited (`CHART_TEMPLATE_APPLIED`).
- `accounting:create-company --template=` and `CompanyService::create(..., template:)` start a company from a template.
- Your own templates in `config('accounting.chart_templates')`.
- Permission `chart-templates.apply` (super-admin).

## [2.13.0] - 2026-10-15

Financial statements and report mapping release.

### Added
- **Report lines**: the lines of the balance sheet and income statement per company, with a standard IFRS-style
  layout seeded for every company (also on upgrade and for new companies). Lines can be renamed, reordered and
  added; each balance sheet line has a cash-flow class (cash, operating, non-cash, investing, financing).
- **Report mapping**: accounts map to lines, groups pass their mapping down, accounts can override the line and
  the cash-flow class. "Apply recommended mapping" maps the seeded chart (done automatically for new installs and
  new companies; `accounting:seed` maps seeded accounts that still carry their seeded names). Audited.
- **Financial statements by lines** (React, Blade, API `GET /reports/statements/{type}`, CSV/Excel/PDF exports):
  balance sheet with comparative date, income statement with gross/operating/before-tax/net profit and a
  comparative period, drill-down to accounts, and the **cash flow statement by the indirect method**, which
  reconciles opening + net change to closing cash. Unmapped accounts are reported on "unmapped" lines.
- Permissions `reports.financial-statements.view` (every role with balance sheet access) and
  `report-mapping.manage` (super-admin, admin).

### Fixed
- (Shipped in 2.12.0, not listed there.) The cost center factory produced types the column refuses (`department`,
  `branch`); tests creating cost centers failed at random on PostgreSQL and MySQL.

## [2.12.0] - 2026-10-14

Renumber and merge accounts release.

### Added
- **Renumber accounts**, including accounts with journal entries: a group can take its sub-accounts along
  (codes sharing its prefix, `5100 → 6100` gives `6101`, `6110` …; the new code keeps the group's length and
  trailing zeros so sub-account codes keep their length). Codes are swapped safely within one
  renumbering; codes in use and accounts named in `config('accounting.defaults')` are refused. Audited
  (`ACCOUNT_RENUMBERED` with the old → new map).
- **Merge a duplicate account into another** without rewriting the ledger: the balance moves with a posted
  transfer entry (one pair of lines per cost center, through the normal posting rules — open period,
  approvals, control accounts), draft lines, sub-accounts and bank accounts follow, and the source is
  deactivated with `metadata.merged_into`. Same type, normal balance, currency and control type only; pending
  approvals on the source must be decided first. Audited (`ACCOUNT_MERGED`).
- Preview of both on a new page (React and Blade, ⇄ icon in the chart list) and in the API:
  `GET /chart-of-accounts/{id}/renumber-preview`, `POST …/renumber`, `GET …/merge-preview`, `POST …/merge`.
- Permission `chart-of-accounts.restructure` (super-admin).

## [2.11.0] - 2026-10-13

Chart of accounts import and export release.

### Added
- **Import the chart of accounts from Excel (.xlsx) or CSV**: Chart of Accounts → Import (React and Blade) or
  `POST /chart-of-accounts/import`. A preview lists every line as new, updated (old → new per field), unchanged
  or error before anything is saved; the import then runs in one transaction and changes nothing if any row
  is wrong. Parents may come after their children in the file; blank cells keep existing values; new accounts
  default to their parent's type and currency. Existing codes are updated (`mode=upsert`) or left alone
  (`mode=create`). Every row passes the API rules and the database guards.
- **Export the chart** (`/chart-of-accounts/export/{csv|xlsx|pdf}`, buttons on the chart screens) in the import
  layout, so it can be edited and imported back; an import **template** with example rows.
- Header aliases (`Code`, `Name`, `Parent`, `Type` …), comma / semicolon / tab CSVs, UTF-8 BOM and Windows-1252
  files. Excel files are read without extra dependencies (PHP zip extension).
- Permission `chart-of-accounts.import` (super-admin); `CHART_IMPORTED` audit record with the codes created and
  updated. Limits `ACCOUNTING_CHART_IMPORT_MAX_KB` / `ACCOUNTING_CHART_IMPORT_MAX_ROWS`.

## [2.10.0] - 2026-10-12

Professional PDFs and background exports release.

### Added
- **Typeset report PDFs** (dompdf, when installed): company letterhead with logo, NTN and address, report title,
  the filters used (period, account, status), right-aligned amount columns, debit/credit totals, "generated by …
  on …" and "Page X of Y" on every page. Without dompdf the built-in renderer is used, now with the company name
  and filters in its header. Settings under `accounting.pdf` (engine, paper, logo, number system).
- **Printable vouchers**: `/accounting/journal-entries/{id}/print` (browser print) and `/pdf` (also
  `GET /api/v1/accounting/journal-entries/{id}/pdf`) — voucher type and number, dates, source document, lines with
  cost centers, totals, **amount in words** (international or Lakh/Crore), Prepared / Approved / Posted / Received
  signature boxes, and a DRAFT / VOID / REVERSED watermark. Print and PDF buttons on the entry page (React, Blade).
- **Background exports**: Excel and PDF exports above `accounting.export_max_rows` are no longer refused — they are
  queued (`GenerateReportExport` job, re-checks the permission when it runs) and appear under **My Exports**
  (React and Blade), downloadable only by the user who asked for them. API: `POST /reports/{report}/exports/{format}`,
  `GET /exports`, `GET /exports/{id}/download`, `DELETE /exports/{id}`. `accounting:prune-exports` removes exports
  older than `accounting.exports.keep_days` (7). `accounting.exports.queue_large=false` keeps the old 422.
- Export buttons (CSV / Excel / PDF / My exports) on every Blade report page.
- `AmountInWords` helper, `PdfRenderer` service, `ReportCatalog` (the exportable reports and their PDF filters).

### Changed
- `dompdf/dompdf` is suggested (not required); install it for typeset PDFs.

## [2.9.0] - 2026-10-11

Users and roles release.

### Added
- **Users and roles screens in React** (Blade already had them): users list filtered by name, email or role, user form with roles
  and a direct-permission matrix, roles list with user and permission counts, role form.
- **API**: `GET/POST /users`, `GET/PUT/DELETE /users/{id}`, `PUT /users/{id}/roles`, `PUT /users/{id}/permissions`,
  `GET/POST /roles`, `GET/PUT/DELETE /roles/{id}`, `GET /permissions`.
- `UserManagementService` behind all three: nobody grants a role or permission they do not hold, only super-admins
  hand out or edit super-admin, nobody deletes themselves, and the super-admin role cannot be renamed or deleted.
- Every user, role and permission change is written to the accounting audit log with old and new values and what
  was added or removed.

### Fixed
- Audit records for models without `company_id` under strict models.
- Roles created through the API used the API guard instead of the default web guard.

## [2.8.0] - 2026-10-10

Attachments release.

### Added
- **Supporting documents on journal entries** (bills, receipts, contracts …): upload several at once from the entry
  page (React and Blade) or `POST /journal-entries/{id}/attachments`; list and download (`GET …/attachments`,
  `GET /attachments/{id}/download`).
- Stored on a private disk (`ACCOUNTING_ATTACHMENTS_DISK`) under `accounting/{company}/{yyyy}/{mm}/`, downloaded
  only through the authorised, company-scoped route; size and type limits (`ACCOUNTING_ATTACHMENTS_MAX_KB`,
  `accounting.attachments.mimes`).
- Documents of posted or voided entries cannot be removed; uploads and removals are audited with the file's
  SHA-256. A file already attached to another entry is flagged as a possible duplicate.
- `ACCOUNTING_ATTACHMENTS_REQUIRED_ABOVE`: entries at or above the amount need a document before posting or
  submission for approval.
- Permissions `attachments.view` (all journal roles), `attachments.create` and `attachments.delete` (accountant).
- `Attachment` model, `JournalEntry::attachments()`, `AttachmentService`.

## [2.7.0] - 2026-10-09

Control accounts release.

### Added
- **Control accounts**: an account can control a sub-ledger (receivables, payables, inventory, fixed assets, payroll,
  tax). Only entries of that module (`origin_module`) post to it; a manual entry (UI or API) is refused unless the
  posting user has `control-accounts.post-manual`. Checked at posting and at submission for approval. Reversals and
  closing entries follow their source entry.
- **Recommended setup** (one click, confirmed) marks the seeded sub-ledger accounts; nothing is marked on upgrade.
- **Control Accounts** screen (React and Blade): balances, the number of manual postings per account with the list
  of those entries, mark / remove control accounts. API: `GET /control-accounts`, `POST /control-accounts/recommended`,
  `GET /control-accounts/{id}/manual-postings`, `PUT /chart-of-accounts/{id}/control-type`; accounts return
  `control_type`.
- `origin_module` on journal entries (`JournalEntryService::create`, `JournalEntry::record(..., module:)`), immutable
  in the database once posted.
- Permissions `control-accounts.manage` (super-admin, admin) and `control-accounts.post-manual` (super-admin).

## [2.6.0] - 2026-10-08

Source documents release.

### Added
- **Source documents** on journal entries: type (sales invoice, purchase bill, receipt, payment voucher,
  credit/debit note, expense claim, payroll sheet, bank statement, contract, other — configurable), number and
  date, plus an optional link to the application model the entry records (`sourceable` morph).
- **A document can be posted only once** per company (type + number, case-insensitive): the second posting gets
  a 422 naming the voucher that holds it. A unique database index makes this hold under concurrent postings.
  Reversing an entry frees its document. `ACCOUNTING_PREVENT_DUPLICATE_DOCUMENTS=false` switches it off.
- The document fields of posted entries are immutable at the database level (MySQL/MariaDB, PostgreSQL, SQLite).
- `HasJournalEntries` trait for application models (`journalEntries()`, `postedJournalEntry()`),
  `JournalEntry::forSource($model)`, and `source` / `documentType` / `documentNumber` on `JournalEntry::record()`.
- Journal form (React and Blade) captures the document; entry page shows it; journal list filters by document
  number; the general ledger (screens, API and exports) shows the voucher number and document number per line.
- API: `source_document_type/number/date` on `POST /journal-entries` and `/journal-entries/simple`,
  `source_document` in responses, `filter[source_document_number]` and `filter[source_document_type]`.

### Fixed
- Saving a journal entry from the Blade (Livewire) form failed validation for every account since 2.4.0: the
  posting-account rule compared `is_group` with an empty string. Rules now use query closures (also for the
  active voucher type check).

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
