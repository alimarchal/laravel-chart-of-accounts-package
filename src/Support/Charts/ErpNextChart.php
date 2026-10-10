<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\Charts;

/**
 * The standard chart of accounts of ERPNext (the "standard chart of accounts with account numbers" template of
 * Frappe Technologies' ERPNext), in this package's shape: Application of Funds (Assets), Source of Funds (Liabilities),
 * Equity, Income and Expenses with ERPNext's groups, names and numbers.
 *
 * ERPNext numbers a range on its two current-asset / current-liability groups ("1100-1600"); a code has to be one number
 * here, so Current Assets is 1050 and Current Liabilities 2050. A few leaf accounts that ERPNext leaves for the user to
 * create are added so every module works out of the box: Primary Bank Account (1210), Input Tax Receivable (1510),
 * Output Sales Tax Payable (2320) and Long-term Investments (1810).
 *
 * Each account is [code, parent code, type, name, group?, options]; options: contra (the account has the opposite normal
 * balance of its type) and line (the statement line it reports under, see ReportLine::defaults()).
 * ERPNext is released under the GPL-3.0; this list follows its published structure and names, which are the common
 * vocabulary of double-entry bookkeeping. See NOTICE.md.
 */
final class ErpNextChart
{
    /**
     * @return list<array{0: string, 1: string|null, 2: string, 3: string, 4?: bool, 5?: array{contra?: bool, line?: string}}>
     */
    public static function rows(): array
    {
        return [
            ['1000', null, 'ASSET', 'Application of Funds (Assets)', true],
            ['1050', '1000', 'ASSET', 'Current Assets', true],
            ['1100', '1050', 'ASSET', 'Cash In Hand', true],
            ['1110', '1100', 'ASSET', 'Cash', false, ['line' => 'BS-CASH']],
            ['1200', '1050', 'ASSET', 'Bank Accounts', true, ['line' => 'BS-CASH']],
            ['1210', '1200', 'ASSET', 'Primary Bank Account'],
            ['1300', '1050', 'ASSET', 'Accounts Receivable', true, ['line' => 'BS-RECEIVABLES']],
            ['1310', '1300', 'ASSET', 'Debtors'],
            ['1400', '1050', 'ASSET', 'Stock Assets', true, ['line' => 'BS-INVENTORIES']],
            ['1410', '1400', 'ASSET', 'Stock In Hand'],
            ['1420', '1400', 'ASSET', 'Stock Delivered But Not Billed'],
            ['1500', '1050', 'ASSET', 'Tax Assets', true, ['line' => 'BS-TAX-ASSET']],
            ['1510', '1500', 'ASSET', 'Input Tax Receivable'],
            ['1600', '1050', 'ASSET', 'Loans and Advances (Assets)', true, ['line' => 'BS-RECEIVABLES']],
            ['1610', '1600', 'ASSET', 'Employee Advances'],
            ['1650', '1050', 'ASSET', 'Securities and Deposits', true, ['line' => 'BS-RECEIVABLES']],
            ['1651', '1650', 'ASSET', 'Earnest Money'],
            ['1660', '1050', 'ASSET', 'Prepaid Expenses', false, ['line' => 'BS-RECEIVABLES']],
            ['1670', '1050', 'ASSET', 'Short-term Investments', false, ['line' => 'BS-INVESTMENTS']],
            ['1700', '1000', 'ASSET', 'Fixed Assets', true, ['line' => 'BS-PPE']],
            ['1710', '1700', 'ASSET', 'Capital Equipment'],
            ['1720', '1700', 'ASSET', 'Electronic Equipment'],
            ['1730', '1700', 'ASSET', 'Furniture and Fixtures'],
            ['1740', '1700', 'ASSET', 'Office Equipment'],
            ['1750', '1700', 'ASSET', 'Plants and Machineries'],
            ['1760', '1700', 'ASSET', 'Buildings'],
            ['1770', '1700', 'ASSET', 'Software'],
            ['1780', '1700', 'ASSET', 'Accumulated Depreciation', false, ['contra' => true, 'line' => 'BS-DEPRECIATION']],
            ['1790', '1700', 'ASSET', 'CWIP Account'],
            ['1800', '1000', 'ASSET', 'Investments', true, ['line' => 'BS-INVESTMENTS']],
            ['1810', '1800', 'ASSET', 'Long-term Investments'],
            ['1900', '1000', 'ASSET', 'Temporary Accounts', true],
            ['1910', '1900', 'ASSET', 'Temporary Opening'],

            ['2000', null, 'LIABILITY', 'Source of Funds (Liabilities)', true],
            ['2050', '2000', 'LIABILITY', 'Current Liabilities', true],
            ['2100', '2050', 'LIABILITY', 'Accounts Payable', true, ['line' => 'BS-PAYABLES']],
            ['2110', '2100', 'LIABILITY', 'Creditors'],
            ['2120', '2100', 'LIABILITY', 'Payroll Payable'],
            ['2150', '2050', 'LIABILITY', 'Accrued Expenses', false, ['line' => 'BS-PAYABLES']],
            ['2160', '2050', 'LIABILITY', 'Customer Advances', false, ['line' => 'BS-DEFERRED-INCOME']],
            ['2200', '2050', 'LIABILITY', 'Stock Liabilities', true, ['line' => 'BS-PAYABLES']],
            ['2210', '2200', 'LIABILITY', 'Stock Received But Not Billed'],
            ['2211', '2200', 'LIABILITY', 'Asset Received But Not Billed'],
            ['2300', '2050', 'LIABILITY', 'Duties and Taxes', true, ['line' => 'BS-TAX-LIABILITY']],
            ['2310', '2300', 'LIABILITY', 'TDS Payable'],
            ['2320', '2300', 'LIABILITY', 'Output Sales Tax Payable'],
            ['2350', '2050', 'LIABILITY', 'Short-term Provisions', false, ['line' => 'BS-PAYABLES']],
            ['2400', '2050', 'LIABILITY', 'Loans (Liabilities)', true, ['line' => 'BS-SHORT-BORROWINGS']],
            ['2410', '2400', 'LIABILITY', 'Secured Loans'],
            ['2420', '2400', 'LIABILITY', 'Unsecured Loans'],
            ['2430', '2400', 'LIABILITY', 'Bank Overdraft Account'],
            ['2500', '2000', 'LIABILITY', 'Non-Current Liabilities', true, ['line' => 'BS-LONG-BORROWINGS']],
            ['2510', '2500', 'LIABILITY', 'Long-term Provisions'],
            ['2520', '2500', 'LIABILITY', 'Employee Benefits Obligation'],

            ['3000', null, 'EQUITY', 'Equity', true],
            ['3100', '3000', 'EQUITY', 'Capital Stock', false, ['line' => 'BS-CAPITAL']],
            ['3200', '3000', 'EQUITY', 'Dividends Paid', false, ['line' => 'BS-RETAINED']],
            ['3300', '3000', 'EQUITY', 'Opening Balance Equity', false, ['line' => 'BS-RETAINED']],
            ['3400', '3000', 'EQUITY', 'Retained Earnings', false, ['line' => 'BS-RETAINED']],
            ['3500', '3000', 'EQUITY', 'Revaluation Surplus', false, ['line' => 'BS-RESERVES']],

            ['4000', null, 'INCOME', 'Income', true],
            ['4100', '4000', 'INCOME', 'Direct Income', true, ['line' => 'IS-REVENUE']],
            ['4110', '4100', 'INCOME', 'Sales'],
            ['4120', '4100', 'INCOME', 'Service'],
            ['4200', '4000', 'INCOME', 'Indirect Income', true, ['line' => 'IS-OTHER-INCOME']],
            ['4210', '4200', 'INCOME', 'Interest Income'],
            ['4220', '4200', 'INCOME', 'Interest on Fixed Deposits'],
            ['4230', '4200', 'INCOME', 'Exchange Gain'],

            ['5000', null, 'EXPENSE', 'Expenses', true],
            ['5100', '5000', 'EXPENSE', 'Direct Expenses', true, ['line' => 'IS-COST-OF-SALES']],
            ['5110', '5100', 'EXPENSE', 'Stock Expenses', true],
            ['5111', '5110', 'EXPENSE', 'Cost of Goods Sold'],
            ['5112', '5110', 'EXPENSE', 'Expenses Included In Asset Valuation'],
            ['5118', '5110', 'EXPENSE', 'Expenses Included In Valuation'],
            ['5119', '5110', 'EXPENSE', 'Stock Adjustment'],
            ['5200', '5000', 'EXPENSE', 'Indirect Expenses', true, ['line' => 'IS-ADMIN']],
            ['5201', '5200', 'EXPENSE', 'Administrative Expenses'],
            ['5202', '5200', 'EXPENSE', 'Commission on Sales'],
            ['5203', '5200', 'EXPENSE', 'Depreciation', false, ['line' => 'IS-DEPRECIATION']],
            ['5204', '5200', 'EXPENSE', 'Entertainment Expenses'],
            ['5205', '5200', 'EXPENSE', 'Freight and Forwarding Charges'],
            ['5206', '5200', 'EXPENSE', 'Legal Expenses'],
            ['5207', '5200', 'EXPENSE', 'Marketing Expenses'],
            ['5208', '5200', 'EXPENSE', 'Office Maintenance Expenses'],
            ['5209', '5200', 'EXPENSE', 'Office Rent'],
            ['5210', '5200', 'EXPENSE', 'Postal Expenses'],
            ['5211', '5200', 'EXPENSE', 'Print and Stationery'],
            ['5212', '5200', 'EXPENSE', 'Round Off', false, ['line' => 'IS-OTHER-EXPENSES']],
            ['5213', '5200', 'EXPENSE', 'Salary'],
            ['5214', '5200', 'EXPENSE', 'Sales Expenses'],
            ['5215', '5200', 'EXPENSE', 'Telephone Expenses'],
            ['5216', '5200', 'EXPENSE', 'Travel Expenses'],
            ['5217', '5200', 'EXPENSE', 'Utility Expenses'],
            ['5218', '5200', 'EXPENSE', 'Write Off', false, ['line' => 'IS-OTHER-EXPENSES']],
            ['5219', '5200', 'EXPENSE', 'Exchange Gain/Loss', false, ['line' => 'IS-OTHER-EXPENSES']],
            ['5220', '5200', 'EXPENSE', 'Interest Expense', false, ['line' => 'IS-FINANCE']],
            ['5221', '5200', 'EXPENSE', 'Bank Charges', false, ['line' => 'IS-FINANCE']],
            ['5222', '5200', 'EXPENSE', 'Gain/Loss on Asset Disposal', false, ['line' => 'IS-OTHER-EXPENSES']],
            ['5223', '5200', 'EXPENSE', 'Miscellaneous Expenses', false, ['line' => 'IS-OTHER-EXPENSES']],
            ['5224', '5200', 'EXPENSE', 'Impairment', false, ['line' => 'IS-OTHER-EXPENSES']],
            ['5225', '5200', 'EXPENSE', 'Tax Expense', false, ['line' => 'IS-TAX']],
            ['5226', '5200', 'EXPENSE', 'Exchange Loss', false, ['line' => 'IS-OTHER-EXPENSES']],
        ];
    }

    /**
     * The accounts in the shape the chart seeder uses.
     *
     * @return list<array<string, mixed>>
     */
    public static function accounts(): array
    {
        return array_map(function (array $row): array {
            $contra = (bool) (($row[5] ?? [])['contra'] ?? false);
            $debit = in_array($row[2], ['ASSET', 'EXPENSE'], true);

            return [
                'account_code' => $row[0], 'parent_code' => $row[1], 'type_code' => $row[2], 'account_name' => $row[3],
                'normal_balance' => ($debit xor $contra) ? 'debit' : 'credit', 'is_group' => (bool) ($row[4] ?? false),
                'line' => ($row[5] ?? [])['line'] ?? null,
            ];
        }, self::rows());
    }

    /**
     * The statement line of each account (code => line code), for the recommended report mapping.
     *
     * @return array<string, string>
     */
    public static function lines(): array
    {
        $lines = [];

        foreach (self::rows() as $row) {
            if (isset($row[5]['line'])) {
                $lines[$row[0]] = $row[5]['line'];
            }
        }

        return $lines;
    }
}
