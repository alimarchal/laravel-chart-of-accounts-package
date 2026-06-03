<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Support\Facades\Schema;

class AccountingHealthCheckService
{
    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $tables = [
            'accounting_account_types',
            'accounting_currencies',
            'accounting_periods',
            'accounting_chart_of_accounts',
            'accounting_journal_entries',
            'accounting_journal_entry_lines',
        ];

        $missingTables = collect($tables)
            ->reject(fn (string $table): bool => Schema::hasTable($table))
            ->values()
            ->all();

        return [
            'ok' => $missingTables === []
                && Currency::query()->where('is_base', true)->count() === 1
                && AccountType::query()->count() >= 5
                && ChartOfAccount::query()->count() > 0,
            'missing_tables' => $missingTables,
            'base_currency_count' => Currency::query()->where('is_base', true)->count(),
            'account_type_count' => AccountType::query()->count(),
            'chart_of_account_count' => ChartOfAccount::query()->count(),
        ];
    }
}
