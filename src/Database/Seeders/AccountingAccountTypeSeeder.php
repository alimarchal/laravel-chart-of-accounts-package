<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountingAccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->types() as $type) {
            AccountType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function types(): array
    {
        return [
            ['code' => 'ASSET', 'name' => 'Asset', 'normal_balance' => 'debit', 'report_group' => 'BalanceSheet', 'description' => 'Economic resources owned or controlled.', 'is_active' => true],
            ['code' => 'LIABILITY', 'name' => 'Liability', 'normal_balance' => 'credit', 'report_group' => 'BalanceSheet', 'description' => 'Present obligations owed to others.', 'is_active' => true],
            ['code' => 'EQUITY', 'name' => 'Equity', 'normal_balance' => 'credit', 'report_group' => 'BalanceSheet', 'description' => 'Owners residual interest.', 'is_active' => true],
            ['code' => 'INCOME', 'name' => 'Income', 'normal_balance' => 'credit', 'report_group' => 'IncomeStatement', 'description' => 'Revenue and other income.', 'is_active' => true],
            ['code' => 'EXPENSE', 'name' => 'Expense', 'normal_balance' => 'debit', 'report_group' => 'IncomeStatement', 'description' => 'Costs incurred to operate.', 'is_active' => true],
        ];
    }
}
