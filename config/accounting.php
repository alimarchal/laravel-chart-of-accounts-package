<?php

return [
    'route_prefix' => env('ACCOUNTING_ROUTE_PREFIX', 'accounting'),
    'route_name_prefix' => env('ACCOUNTING_ROUTE_NAME_PREFIX', 'accounting'),
    'settings_route_prefix' => env('SETTINGS_ROUTE_PREFIX', 'settings'),
    'settings_route_name_prefix' => env('SETTINGS_ROUTE_NAME_PREFIX', 'settings'),
    // 'inertia' (React), 'blade' (Livewire) or 'api' (REST API only — no web routes, views or Livewire loaded).
    'ui_driver' => env('ACCOUNTING_UI_DRIVER', 'inertia'),
    'api_prefix' => env('ACCOUNTING_API_PREFIX', 'api/v1/accounting'),
    // Table referenced by created_by / updated_by / posted_by foreign keys in the package migrations.
    'users_table' => env('ACCOUNTING_USERS_TABLE', 'users'),
    'api_middleware' => array_values(array_filter(explode(',', env('ACCOUNTING_API_MIDDLEWARE', 'api,auth:sanctum')))),

    // Several companies in one database. Off: everything belongs to the default company and no company
    // switching or access checks happen (single-company installs behave exactly as before).
    // On: users see only the companies they are assigned to (super-admin sees all); the web UI keeps
    // the current company in the session, the API takes it from the X-Company header (id or code).
    'multi_company' => [
        'enabled' => (bool) env('ACCOUNTING_MULTI_COMPANY', false),
        'header' => env('ACCOUNTING_COMPANY_HEADER', 'X-Company'),
        'default_company_code' => env('ACCOUNTING_DEFAULT_COMPANY', 'MAIN'),
    ],

    // REST API switches and limits.
    'api_enabled' => (bool) env('ACCOUNTING_API_ENABLED', true),
    'api_rate_limit' => (int) env('ACCOUNTING_API_RATE_LIMIT', 120), // requests per minute per user/IP; 0 disables
    'api_max_per_page' => (int) env('ACCOUNTING_API_MAX_PER_PAGE', 100),
    // Report exports: CSV is streamed with constant memory at any size; XLSX and PDF are built in
    // memory, so they are refused (HTTP 422, "use CSV or narrow the filters") above these row counts.
    'export_max_rows' => [
        'xlsx' => (int) env('ACCOUNTING_EXPORT_MAX_XLSX_ROWS', 50000),
        'pdf' => (int) env('ACCOUNTING_EXPORT_MAX_PDF_ROWS', 2000),
    ],

    'defaults' => [
        'currency_code' => env('ACCOUNTING_BASE_CURRENCY', 'PKR'),
        'cash_account_code' => env('ACCOUNTING_CASH_ACCOUNT_CODE', '1101'),
        'bank_account_code' => env('ACCOUNTING_BANK_ACCOUNT_CODE', '1102'),
        'retained_earnings_account_code' => env('ACCOUNTING_RETAINED_EARNINGS_ACCOUNT_CODE', '3101'),
        'rounding_account_code' => env('ACCOUNTING_ROUNDING_ACCOUNT_CODE', '5201'),
    ],

    // Maker-checker. When enabled, entries whose total (in base currency) is at or above the threshold
    // must be submitted by the maker and approved by a different user (the checker) before they post.
    'approvals' => [
        'enabled' => (bool) env('ACCOUNTING_APPROVALS_ENABLED', false),
        'threshold' => env('ACCOUNTING_APPROVAL_THRESHOLD', '0'), // 0 = every entry needs approval
        'allow_self_approval' => (bool) env('ACCOUNTING_ALLOW_SELF_APPROVAL', false),
    ],

    // Source documents a journal entry can refer to (type key => label). With prevent_duplicates, a document
    // (type + number) can be posted only once per company until that entry is reversed.
    'source_documents' => [
        'prevent_duplicates' => (bool) env('ACCOUNTING_PREVENT_DUPLICATE_DOCUMENTS', true),
        'types' => [
            'invoice' => 'Sales invoice',
            'credit_note' => 'Credit note',
            'bill' => 'Purchase bill',
            'debit_note' => 'Debit note',
            'receipt' => 'Receipt',
            'payment' => 'Payment voucher',
            'expense_claim' => 'Expense claim',
            'payroll' => 'Payroll sheet',
            'bank_statement' => 'Bank statement',
            'contract' => 'Contract / agreement',
            'other' => 'Other document',
        ],
    ],

    // Control accounts summarise a sub-ledger (customers, suppliers, stock, …). Only entries of that module may
    // post to them; a manual entry needs the control-accounts.post-manual permission. "recommended" maps the
    // seeded chart (account code => type) for the Recommended setup action.
    'control_accounts' => [
        'types' => [
            'receivables' => 'Accounts receivable (customers)',
            'payables' => 'Accounts payable (suppliers)',
            'inventory' => 'Inventory (stock)',
            'fixed_assets' => 'Fixed assets',
            'payroll' => 'Payroll',
            'tax' => 'Tax',
        ],
        'recommended' => [
            '1103' => 'receivables',
            '2101' => 'payables',
            '1151' => 'inventory',
            '1152' => 'inventory',
            '1153' => 'inventory',
            '2103' => 'payroll',
            '1107' => 'tax',
            '2104' => 'tax',
        ],
    ],

    // PDF reports and printed vouchers. engine: auto (dompdf when installed, else the built-in renderer),
    // dompdf or builtin. The company logo is read from logo_disk (Company::logo_path) or from logo (a file path).
    // number_system: international (million) or south_asian (lakh, crore) for amounts in words.
    'pdf' => [
        'engine' => env('ACCOUNTING_PDF_ENGINE', 'auto'),
        'paper' => env('ACCOUNTING_PDF_PAPER', 'a4'),
        'logo_disk' => env('ACCOUNTING_PDF_LOGO_DISK', 'public'),
        'logo' => env('ACCOUNTING_PDF_LOGO'),
        'number_system' => env('ACCOUNTING_NUMBER_SYSTEM', 'international'),
        // Above this many rows a PDF uses the built-in renderer (typesetting large tables is slow).
        'dompdf_max_rows' => (int) env('ACCOUNTING_PDF_DOMPDF_MAX_ROWS', 3000),
    ],

    // Chart of accounts import (CSV / XLSX): the largest file accepted and the most rows it may hold.
    'chart_import' => [
        'max_size_kb' => (int) env('ACCOUNTING_CHART_IMPORT_MAX_KB', 5120),
        'max_rows' => (int) env('ACCOUNTING_CHART_IMPORT_MAX_ROWS', 5000),
    ],

    // Exports above export_max_rows are generated in the background (queue) and listed under Exports.
    'exports' => [
        'queue_large' => (bool) env('ACCOUNTING_QUEUE_LARGE_EXPORTS', true),
        'disk' => env('ACCOUNTING_EXPORTS_DISK', 'local'),
        'max_rows' => ['xlsx' => 500000, 'pdf' => 50000],
        'keep_days' => (int) env('ACCOUNTING_EXPORTS_KEEP_DAYS', 7),
    ],

    // Supporting documents (scanned bills, receipts, contracts) attached to journal entries. Files are stored on
    // a private disk and only downloaded through the package's authorised route. Attachments of posted entries
    // cannot be removed. required_above: entries whose total (base currency) is at or above this amount need at
    // least one attachment before they are posted or submitted for approval (null = never required).
    'attachments' => [
        'disk' => env('ACCOUNTING_ATTACHMENTS_DISK', 'local'),
        'max_size_kb' => (int) env('ACCOUNTING_ATTACHMENTS_MAX_KB', 10240),
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'tif', 'tiff', 'xls', 'xlsx', 'csv', 'doc', 'docx', 'txt', 'xml', 'zip'],
        'required_above' => env('ACCOUNTING_ATTACHMENTS_REQUIRED_ABOVE'),
    ],

    // Signed webhooks for accounting events (queued when a queue is configured). Empty = disabled.
    'webhooks' => [
        'urls' => array_values(array_filter(explode(',', (string) env('ACCOUNTING_WEBHOOK_URLS', '')))),
        'secret' => env('ACCOUNTING_WEBHOOK_SECRET'),
        'timeout' => (int) env('ACCOUNTING_WEBHOOK_TIMEOUT', 10),
        'tries' => (int) env('ACCOUNTING_WEBHOOK_TRIES', 5),
    ],

    // Accounts (and their child accounts) included in the aged receivables / payables reports.
    'aging' => [
        'receivable_account_codes' => ['1103', '1104'],
        'payable_account_codes' => ['2101', '2102', '2103', '2104'],
    ],

    // Chart of accounts seeded by accounting:seed for NEW accounts: 'general' or 'school'.
    // Existing accounts are never overwritten by the seeder.
    'chart_preset' => env('ACCOUNTING_CHART_PRESET', 'general'),

    'permissions' => [
        'accounting.view',
        'accounting.manage-settings',
        'companies.manage',
        'reports.consolidated.view',
        'user.view',
        'user.create',
        'user.update',
        'user.delete',
        'user.assign-role',
        'user.assign-permission',
        'account-types.view',
        'account-types.create',
        'account-types.update',
        'account-types.delete',
        'currencies.view',
        'currencies.create',
        'currencies.update',
        'currencies.delete',
        'periods.view',
        'periods.create',
        'periods.update',
        'periods.delete',
        'periods.close',
        'periods.reopen',
        'chart-of-accounts.view',
        'chart-of-accounts.create',
        'chart-of-accounts.update',
        'chart-of-accounts.import',
        'chart-of-accounts.restructure',
        'report-mapping.manage',
        'chart-of-accounts.delete',
        'cost-centers.view',
        'cost-centers.create',
        'cost-centers.update',
        'cost-centers.delete',
        'journal-entries.view',
        'journal-entries.create',
        'journal-entries.update',
        'journal-entries.delete',
        'journal-entries.post',
        'journal-entries.reverse',
        'journal-entries.void',
        'journal-entries.approve',
        'bank-accounts.view',
        'bank-accounts.create',
        'bank-accounts.update',
        'bank-accounts.delete',
        'reconciliations.view',
        'reconciliations.create',
        'reconciliations.update',
        'reconciliations.delete',
        'tax-codes.view',
        'tax-codes.create',
        'tax-codes.update',
        'tax-codes.delete',
        'tax-rates.view',
        'tax-rates.create',
        'tax-rates.update',
        'tax-rates.delete',
        'voucher-types.view',
        'voucher-types.create',
        'voucher-types.update',
        'voucher-types.delete',
        'control-accounts.manage',
        'control-accounts.post-manual',
        'attachments.view',
        'attachments.create',
        'attachments.delete',
        'account-balance-snapshots.view',
        'reports.general-ledger.view',
        'reports.trial-balance.view',
        'reports.balance-sheet.view',
        'reports.financial-statements.view',
        'reports.income-statement.view',
        'reports.cash-flow.view',
        'reports.aged-receivables.view',
        'reports.aged-payables.view',
        'reports.account-statement.view',
        'reports.account-balances.view',
        'reports.bank-book.view',
        'reports.cash-book.view',
        'audit-logs.view',
    ],

    /*
     * Roles follow segregation of duties (see README "Roles & permissions" and `php artisan accounting:roles`):
     *  - super-admin: everything (still cannot approve its own entries under maker-checker);
     *  - admin: users, roles and permissions — no accounting writes;
     *  - accountant: the maker — records, posts (below the approval threshold), reverses, closes periods;
     *  - approver: the checker — approves or rejects entries, cannot create or edit them;
     *  - auditor: read-only access to everything, including the audit trail;
     *  - viewer: read-only access to the ledger and reports.
     * Re-running accounting:seed never removes permissions you changed; it only adds newly introduced ones.
     */
    'roles' => [
        'super-admin' => ['*'],
        'admin' => [
            'accounting.view',
            'accounting.manage-settings',
            'user.view',
            'user.create',
            'user.update',
            'user.assign-role',
            'user.assign-permission',
            'chart-of-accounts.view',
            'journal-entries.view',
            'reports.trial-balance.view',
            'companies.manage',
            'voucher-types.view',
            'voucher-types.create',
            'voucher-types.update',
            'voucher-types.delete',
            'control-accounts.manage',
            'report-mapping.manage',
            'reports.balance-sheet.view',
            'reports.financial-statements.view',
            'reports.income-statement.view',
        ],
        'accountant' => [
            'accounting.view',
            'account-types.view',
            'currencies.view',
            'periods.view',
            'chart-of-accounts.view',
            'cost-centers.view',
            'journal-entries.view',
            'attachments.view',
            'attachments.create',
            'attachments.delete',
            'bank-accounts.view',
            'reconciliations.view',
            'tax-codes.view',
            'tax-rates.view',
            'voucher-types.view',
            'account-balance-snapshots.view',
            'currencies.update',
            'periods.close',
            'cost-centers.create',
            'cost-centers.update',
            'journal-entries.create',
            'journal-entries.update',
            'journal-entries.post',
            'journal-entries.reverse',
            'journal-entries.void',
            'bank-accounts.create',
            'bank-accounts.update',
            'reconciliations.create',
            'reconciliations.update',
            'tax-codes.create',
            'tax-codes.update',
            'tax-rates.create',
            'tax-rates.update',
            'reports.general-ledger.view',
            'reports.trial-balance.view',
            'reports.consolidated.view',
            'reports.balance-sheet.view',
            'reports.financial-statements.view',
            'reports.income-statement.view',
            'reports.cash-flow.view',
            'reports.aged-receivables.view',
            'reports.aged-payables.view',
            'reports.account-statement.view',
            'reports.account-balances.view',
            'reports.bank-book.view',
            'reports.cash-book.view',
            'audit-logs.view',
        ],
        'approver' => [
            'accounting.view',
            'chart-of-accounts.view',
            'journal-entries.view',
            'attachments.view',
            'journal-entries.approve',
            'account-balance-snapshots.view',
            'reports.general-ledger.view',
            'reports.trial-balance.view',
            'reports.balance-sheet.view',
            'reports.financial-statements.view',
            'reports.income-statement.view',
            'reports.cash-flow.view',
            'reports.aged-receivables.view',
            'reports.aged-payables.view',
            'reports.account-statement.view',
            'reports.account-balances.view',
            'reports.bank-book.view',
            'reports.cash-book.view',
        ],
        'auditor' => [
            'accounting.view',
            'account-types.view',
            'currencies.view',
            'periods.view',
            'chart-of-accounts.view',
            'cost-centers.view',
            'journal-entries.view',
            'attachments.view',
            'bank-accounts.view',
            'reconciliations.view',
            'tax-codes.view',
            'tax-rates.view',
            'voucher-types.view',
            'account-balance-snapshots.view',
            'reports.general-ledger.view',
            'reports.trial-balance.view',
            'reports.consolidated.view',
            'reports.balance-sheet.view',
            'reports.financial-statements.view',
            'reports.income-statement.view',
            'reports.cash-flow.view',
            'reports.aged-receivables.view',
            'reports.aged-payables.view',
            'reports.account-statement.view',
            'reports.account-balances.view',
            'reports.bank-book.view',
            'reports.cash-book.view',
            'audit-logs.view',
        ],
        'viewer' => [
            'accounting.view',
            'chart-of-accounts.view',
            'journal-entries.view',
            'attachments.view',
            'tax-codes.view',
            'tax-rates.view',
            'voucher-types.view',
            'account-balance-snapshots.view',
            'reports.general-ledger.view',
            'reports.trial-balance.view',
            'reports.consolidated.view',
            'reports.balance-sheet.view',
            'reports.financial-statements.view',
            'reports.income-statement.view',
            'reports.cash-flow.view',
            'reports.aged-receivables.view',
            'reports.aged-payables.view',
            'reports.account-statement.view',
            'reports.account-balances.view',
            'reports.bank-book.view',
            'reports.cash-book.view',
        ],
    ],
];
