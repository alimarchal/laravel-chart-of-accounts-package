<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\Install;

/**
 * Every optional .env setting of the package, grouped by topic, for the installer. Each group is offered with one yes/no question
 * (all of them with --advanced); the questions in it show the current or default value, and what is left empty is not written.
 *
 * Question: env (the key), label, type (text | int | bool | choice | secret | secret-or-random | url | time), default, choices, when
 * (an env key => value that an earlier answer must have), forced (written as true when the group is accepted).
 */
final class EnvQuestions
{
    /**
     * @return list<array{key: string, gate: string, title: string, off?: array<string, bool|string>, questions: list<array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'api', 'title' => 'REST API', 'gate' => 'Do you want to use the REST API? (needs Laravel Sanctum: "php artisan install:api")',
                'off' => ['ACCOUNTING_API_ENABLED' => false],
                'questions' => [
                    ['env' => 'ACCOUNTING_API_ENABLED', 'forced' => true],
                    ['env' => 'ACCOUNTING_API_PREFIX', 'label' => 'URL of the API', 'type' => 'text', 'default' => 'api/v1/accounting'],
                    ['env' => 'ACCOUNTING_API_RATE_LIMIT', 'label' => 'API calls per minute and user (0 = no limit)', 'type' => 'int', 'default' => 120],
                    ['env' => 'ACCOUNTING_API_MAX_PER_PAGE', 'label' => 'Most rows an API list returns in one page', 'type' => 'int', 'default' => 100],
                ],
            ],
            [
                'key' => 'whatsapp', 'title' => 'WhatsApp', 'gate' => 'Do you want to send WhatsApp messages (receipts, reminders)? You can start in the sandbox without any Meta account.',
                'questions' => [
                    ['env' => 'ACCOUNTING_WHATSAPP_ENABLED', 'forced' => true],
                    ['env' => 'ACCOUNTING_WHATSAPP_MODE', 'label' => 'WhatsApp mode: fake (sandbox, nothing is sent) or live (Meta)', 'type' => 'choice', 'choices' => ['fake', 'live'], 'default' => 'fake'],
                    ['env' => 'WHATSAPP_TOKEN', 'label' => 'WhatsApp access token (system user token)', 'type' => 'secret', 'when' => ['ACCOUNTING_WHATSAPP_MODE' => 'live']],
                    ['env' => 'WHATSAPP_PHONE_NUMBER_ID', 'label' => 'WhatsApp phone number ID', 'type' => 'text', 'when' => ['ACCOUNTING_WHATSAPP_MODE' => 'live']],
                    ['env' => 'WHATSAPP_BUSINESS_ACCOUNT_ID', 'label' => 'WhatsApp Business Account ID (to list templates)', 'type' => 'text', 'when' => ['ACCOUNTING_WHATSAPP_MODE' => 'live']],
                    ['env' => 'WHATSAPP_APP_SECRET', 'label' => 'Meta app secret (signs the webhook calls)', 'type' => 'secret', 'when' => ['ACCOUNTING_WHATSAPP_MODE' => 'live']],
                    ['env' => 'WHATSAPP_VERIFY_TOKEN', 'label' => 'Verify token for Meta\'s webhook', 'type' => 'secret-or-random'],
                    ['env' => 'WHATSAPP_DEFAULT_COUNTRY_CODE', 'label' => 'Country code for numbers written with a leading 0 (92 for Pakistan)', 'type' => 'text', 'default' => '92'],
                    ['env' => 'WHATSAPP_DEFAULT_LANGUAGE', 'label' => 'Language code of your WhatsApp templates (en, en_US, ur …)', 'type' => 'text', 'default' => 'en'],
                    ['env' => 'WHATSAPP_NOTIFY_RECEIPT', 'label' => 'Template to send the customer when a receipt is recorded (empty = none)', 'type' => 'text', 'default' => ''],
                    ['env' => 'WHATSAPP_USE_QUEUE', 'label' => 'Send the automatic messages through the queue?', 'type' => 'bool', 'default' => false],
                ],
            ],
            [
                'key' => 'webhooks', 'title' => 'Signed webhooks', 'gate' => 'Do you want to send events (entry posted, period closed …) to another system as signed webhooks?',
                'questions' => [
                    ['env' => 'ACCOUNTING_WEBHOOK_URLS', 'label' => 'Webhook URL(s), comma separated', 'type' => 'text', 'default' => ''],
                    ['env' => 'ACCOUNTING_WEBHOOK_SECRET', 'label' => 'Secret that signs the webhooks', 'type' => 'secret-or-random'],
                    ['env' => 'ACCOUNTING_WEBHOOK_TRIES', 'label' => 'Delivery attempts', 'type' => 'int', 'default' => 5],
                    ['env' => 'ACCOUNTING_WEBHOOK_TIMEOUT', 'label' => 'Seconds to wait for the receiver', 'type' => 'int', 'default' => 10],
                ],
            ],
            [
                'key' => 'payroll', 'title' => 'Payroll', 'gate' => 'Do you want to set the payroll options (working days, overtime, gratuity, approval)?',
                'questions' => [
                    ['env' => 'ACCOUNTING_PAYROLL_DAY_BASIS', 'label' => 'How a day\'s pay is worked out: calendar (the real 28-31 days), fixed (the days below every month) or working (month less weekly off days)', 'type' => 'choice', 'choices' => ['calendar', 'fixed', 'working'], 'default' => 'calendar'],
                    ['env' => 'ACCOUNTING_PAYROLL_DAYS_PER_MONTH', 'label' => 'Days in a salary month (used when the day basis is fixed)', 'type' => 'int', 'default' => 30],
                    ['env' => 'ACCOUNTING_PAYROLL_REQUIRE_APPROVAL', 'label' => 'HR submits and finance approves every payroll run?', 'type' => 'bool', 'default' => false],
                    ['env' => 'ACCOUNTING_PAYROLL_AUTO_RUN', 'label' => 'Create the payroll run automatically every month?', 'type' => 'bool', 'default' => false],
                    ['env' => 'ACCOUNTING_PAYROLL_AUTO_RUN_DAY', 'label' => 'Day of the month for the automatic run', 'type' => 'int', 'default' => 1, 'when' => ['ACCOUNTING_PAYROLL_AUTO_RUN' => 'true']],
                    ['env' => 'ACCOUNTING_PAYROLL_AUTO_RUN_TIME', 'label' => 'Time of the automatic run (HH:MM)', 'type' => 'time', 'default' => '03:00', 'when' => ['ACCOUNTING_PAYROLL_AUTO_RUN' => 'true']],
                    ['env' => 'ACCOUNTING_PAYROLL_OVERTIME_HOURS', 'label' => 'Working hours in a month for the overtime rate', 'type' => 'int', 'default' => 208],
                    ['env' => 'ACCOUNTING_PAYROLL_OVERTIME_MULTIPLIER', 'label' => 'Overtime rate (times the hourly pay)', 'type' => 'text', 'default' => '2'],
                    ['env' => 'ACCOUNTING_PAYROLL_OVERTIME_HOLIDAY_MULTIPLIER', 'label' => 'Overtime rate on holidays', 'type' => 'text', 'default' => '2'],
                    ['env' => 'ACCOUNTING_PAYROLL_GRATUITY_DAYS', 'label' => 'Gratuity: days of pay per year of service', 'type' => 'int', 'default' => 30],
                    ['env' => 'ACCOUNTING_PAYROLL_GRATUITY_MIN_YEARS', 'label' => 'Gratuity: years of service needed', 'type' => 'int', 'default' => 1],
                    ['env' => 'ACCOUNTING_PAYROLL_TAX_YEAR_START', 'label' => 'Month the tax year starts (1-12; 7 = July)', 'type' => 'int', 'default' => 7],
                ],
            ],
            [
                'key' => 'budgets_bank', 'title' => 'Budgets and bank statements', 'gate' => 'Do you want to set budget control and bank statement import options?',
                'questions' => [
                    ['env' => 'ACCOUNTING_BUDGET_CONTROL', 'label' => 'Budget control: off, warn or block', 'type' => 'choice', 'choices' => ['off', 'warn', 'block'], 'default' => 'off'],
                    ['env' => 'ACCOUNTING_BUDGET_WARN_PERCENT', 'label' => 'Warn when this percent of a budget is used', 'type' => 'int', 'default' => 90],
                    ['env' => 'ACCOUNTING_BANK_DATE_ORDER', 'label' => 'Date order in your bank statements: dmy, mdy or ymd', 'type' => 'choice', 'choices' => ['dmy', 'mdy', 'ymd'], 'default' => 'dmy'],
                    ['env' => 'ACCOUNTING_BANK_MATCH_DAYS', 'label' => 'Days either side of a bank line that still match a ledger entry', 'type' => 'int', 'default' => 5],
                ],
            ],
            [
                'key' => 'files', 'title' => 'PDF, exports and attachments', 'gate' => 'Do you want to set the PDF, export and attachment options?',
                'questions' => [
                    ['env' => 'ACCOUNTING_PDF_PAPER', 'label' => 'Paper size of PDFs (a4, letter …)', 'type' => 'text', 'default' => 'a4'],
                    ['env' => 'ACCOUNTING_PDF_ENGINE', 'label' => 'PDF engine: auto, dompdf or builtin', 'type' => 'choice', 'choices' => ['auto', 'dompdf', 'builtin'], 'default' => 'auto'],
                    ['env' => 'ACCOUNTING_NUMBER_SYSTEM', 'label' => 'Amounts in words: international (million) or south_asian (lakh, crore)', 'type' => 'choice', 'choices' => ['international', 'south_asian'], 'default' => 'international'],
                    ['env' => 'ACCOUNTING_QUEUE_LARGE_EXPORTS', 'label' => 'Build large exports in the background (queue)?', 'type' => 'bool', 'default' => true],
                    ['env' => 'ACCOUNTING_EXPORTS_KEEP_DAYS', 'label' => 'Days to keep finished exports', 'type' => 'int', 'default' => 7],
                    ['env' => 'ACCOUNTING_ATTACHMENTS_MAX_KB', 'label' => 'Largest attachment in KB', 'type' => 'int', 'default' => 10240],
                    ['env' => 'ACCOUNTING_ATTACHMENTS_REQUIRED_ABOVE', 'label' => 'Entries from this amount need an attachment (empty = never)', 'type' => 'text', 'default' => ''],
                ],
            ],
            [
                'key' => 'language', 'title' => 'Language', 'gate' => 'Do you want to change the language of the screens?',
                'questions' => [
                    ['env' => 'ACCOUNTING_LOCALE', 'label' => 'Language: en (English) or ur (Urdu, right to left)', 'type' => 'choice', 'choices' => ['en', 'ur'], 'default' => 'en'],
                ],
            ],
            [
                'key' => 'modules', 'title' => 'Modules', 'gate' => 'Are there modules you will not use and want to switch off?',
                'questions' => [
                    ['env' => 'ACCOUNTING_FEATURES_DISABLED', 'label' => 'Modules to switch off', 'type' => 'features'],
                ],
            ],
        ];
    }
}
