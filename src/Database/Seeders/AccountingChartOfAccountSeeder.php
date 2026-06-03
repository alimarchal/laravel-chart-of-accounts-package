<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Database\Seeder;

class AccountingChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $baseCurrency = Currency::query()->where('is_base', true)->firstOrFail();
        $types = AccountType::query()->pluck('id', 'code');

        foreach ($this->accounts() as $account) {
            $parentId = null;

            if ($account['parent_code'] !== null) {
                $parentId = ChartOfAccount::query()
                    ->where('account_code', $account['parent_code'])
                    ->value('id');
            }

            ChartOfAccount::query()->updateOrCreate(
                ['account_code' => $account['account_code']],
                [
                    'parent_id' => $parentId,
                    'account_type_id' => $types[$account['type_code']],
                    'currency_id' => $baseCurrency->id,
                    'account_name' => $account['account_name'],
                    'normal_balance' => $account['normal_balance'],
                    'description' => $account['description'] ?? null,
                    'is_group' => $account['is_group'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        return [
            ['account_code' => '1000', 'parent_code' => null, 'type_code' => 'ASSET', 'account_name' => 'Assets', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '1100', 'parent_code' => '1000', 'type_code' => 'ASSET', 'account_name' => 'Current Assets', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '1101', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Cash In Hand', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1102', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Bank Accounts', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '1103', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Accounts Receivable', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1104', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Student Fee Receivable', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1105', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Employee Advances', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1106', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Security Deposits', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1107', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Tax Receivable', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1108', 'parent_code' => '1102', 'type_code' => 'ASSET', 'account_name' => 'Operating Bank Account', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1109', 'parent_code' => '1102', 'type_code' => 'ASSET', 'account_name' => 'Payroll Bank Account', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1110', 'parent_code' => '1102', 'type_code' => 'ASSET', 'account_name' => 'Petty Cash Bank Float', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1150', 'parent_code' => '1100', 'type_code' => 'ASSET', 'account_name' => 'Inventory Assets', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '1151', 'parent_code' => '1150', 'type_code' => 'ASSET', 'account_name' => 'Books Inventory', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1152', 'parent_code' => '1150', 'type_code' => 'ASSET', 'account_name' => 'Uniform Inventory', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1153', 'parent_code' => '1150', 'type_code' => 'ASSET', 'account_name' => 'Stationery Inventory', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1200', 'parent_code' => '1000', 'type_code' => 'ASSET', 'account_name' => 'Fixed Assets', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '1201', 'parent_code' => '1200', 'type_code' => 'ASSET', 'account_name' => 'Furniture and Fixtures', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1202', 'parent_code' => '1200', 'type_code' => 'ASSET', 'account_name' => 'Computer Equipment', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1203', 'parent_code' => '1200', 'type_code' => 'ASSET', 'account_name' => 'Lab Equipment', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1204', 'parent_code' => '1200', 'type_code' => 'ASSET', 'account_name' => 'Library Equipment', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1205', 'parent_code' => '1200', 'type_code' => 'ASSET', 'account_name' => 'Vehicles', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1206', 'parent_code' => '1200', 'type_code' => 'ASSET', 'account_name' => 'Accumulated Depreciation', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '1300', 'parent_code' => '1000', 'type_code' => 'ASSET', 'account_name' => 'Long Term Assets', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '1301', 'parent_code' => '1300', 'type_code' => 'ASSET', 'account_name' => 'Long Term Investments', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '1302', 'parent_code' => '1300', 'type_code' => 'ASSET', 'account_name' => 'Capital Work In Progress', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '2000', 'parent_code' => null, 'type_code' => 'LIABILITY', 'account_name' => 'Liabilities', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '2100', 'parent_code' => '2000', 'type_code' => 'LIABILITY', 'account_name' => 'Current Liabilities', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '2101', 'parent_code' => '2100', 'type_code' => 'LIABILITY', 'account_name' => 'Accounts Payable', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '2102', 'parent_code' => '2100', 'type_code' => 'LIABILITY', 'account_name' => 'Accrued Expenses', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '2103', 'parent_code' => '2100', 'type_code' => 'LIABILITY', 'account_name' => 'Salary Payable', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '2104', 'parent_code' => '2100', 'type_code' => 'LIABILITY', 'account_name' => 'Tax Payable', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '2105', 'parent_code' => '2100', 'type_code' => 'LIABILITY', 'account_name' => 'Student Security Deposits Payable', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '2106', 'parent_code' => '2100', 'type_code' => 'LIABILITY', 'account_name' => 'Unearned Fee Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '2200', 'parent_code' => '2000', 'type_code' => 'LIABILITY', 'account_name' => 'Loans and Borrowings', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '2201', 'parent_code' => '2200', 'type_code' => 'LIABILITY', 'account_name' => 'Short Term Loan', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '2202', 'parent_code' => '2200', 'type_code' => 'LIABILITY', 'account_name' => 'Long Term Loan', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '3000', 'parent_code' => null, 'type_code' => 'EQUITY', 'account_name' => 'Equity', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '3100', 'parent_code' => '3000', 'type_code' => 'EQUITY', 'account_name' => 'Capital and Reserves', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '3101', 'parent_code' => '3000', 'type_code' => 'EQUITY', 'account_name' => 'Retained Earnings', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '3102', 'parent_code' => '3000', 'type_code' => 'EQUITY', 'account_name' => 'Opening Balance Equity', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '3103', 'parent_code' => '3100', 'type_code' => 'EQUITY', 'account_name' => 'Owner Capital', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '3104', 'parent_code' => '3100', 'type_code' => 'EQUITY', 'account_name' => 'Owner Drawings', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '3105', 'parent_code' => '3100', 'type_code' => 'EQUITY', 'account_name' => 'Revaluation Surplus', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4000', 'parent_code' => null, 'type_code' => 'INCOME', 'account_name' => 'Income', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '4100', 'parent_code' => '4000', 'type_code' => 'INCOME', 'account_name' => 'Operating Income', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '4101', 'parent_code' => '4100', 'type_code' => 'INCOME', 'account_name' => 'Tuition Fee Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4102', 'parent_code' => '4100', 'type_code' => 'INCOME', 'account_name' => 'Admission Fee Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4103', 'parent_code' => '4100', 'type_code' => 'INCOME', 'account_name' => 'Exam Fee Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4104', 'parent_code' => '4100', 'type_code' => 'INCOME', 'account_name' => 'Transport Fee Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4105', 'parent_code' => '4100', 'type_code' => 'INCOME', 'account_name' => 'Library Fee Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4106', 'parent_code' => '4100', 'type_code' => 'INCOME', 'account_name' => 'Lab Fee Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4200', 'parent_code' => '4000', 'type_code' => 'INCOME', 'account_name' => 'Other Income', 'normal_balance' => 'credit', 'is_group' => true],
            ['account_code' => '4201', 'parent_code' => '4200', 'type_code' => 'INCOME', 'account_name' => 'Bank Profit Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4202', 'parent_code' => '4200', 'type_code' => 'INCOME', 'account_name' => 'Fine Income', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4203', 'parent_code' => '4200', 'type_code' => 'INCOME', 'account_name' => 'Discount Received', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '4204', 'parent_code' => '4200', 'type_code' => 'INCOME', 'account_name' => 'Asset Disposal Gain', 'normal_balance' => 'credit', 'is_group' => false],
            ['account_code' => '5000', 'parent_code' => null, 'type_code' => 'EXPENSE', 'account_name' => 'Expenses', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '5100', 'parent_code' => '5000', 'type_code' => 'EXPENSE', 'account_name' => 'Operating Expenses', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '5101', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Salary Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5102', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Rent Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5103', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Utilities Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5104', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Stationery Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5105', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Printing Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5106', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Repair and Maintenance', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5107', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Fuel Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5108', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Vehicle Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5109', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Internet and IT Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5110', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Security Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5111', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Cleaning Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5112', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Professional Fee Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5113', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Bank Charges', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5114', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Depreciation Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5115', 'parent_code' => '5100', 'type_code' => 'EXPENSE', 'account_name' => 'Bad Debt Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5200', 'parent_code' => '5000', 'type_code' => 'EXPENSE', 'account_name' => 'Inventory and Academic Expenses', 'normal_balance' => 'debit', 'is_group' => true],
            ['account_code' => '5201', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Rounding Difference', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5202', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Books Cost of Goods Sold', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5203', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Uniform Cost of Goods Sold', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5204', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Stationery Cost of Goods Sold', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5205', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Stock Loss - Damage', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5206', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Stock Loss - Expiry', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5207', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Scholarship and Concession Expense', 'normal_balance' => 'debit', 'is_group' => false],
            ['account_code' => '5208', 'parent_code' => '5200', 'type_code' => 'EXPENSE', 'account_name' => 'Discount Difference Expense', 'normal_balance' => 'debit', 'is_group' => false],
        ];
    }
}
