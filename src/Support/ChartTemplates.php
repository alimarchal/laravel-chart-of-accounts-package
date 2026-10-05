<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

/**
 * Industry chart templates: a base chart ('general' or 'school', the seeded charts) plus the accounts that industry
 * adds. Add your own with config('accounting.chart_templates') in the same shape.
 *
 * An extra account is [code, parent code, type, name, group?, options] with options: contra (the account has the
 * opposite normal balance of its type) and line (the statement line code of ReportLine::defaults()).
 */
final class ChartTemplates
{
    /**
     * @return array<string, array{name: string, description: string, base: string, extras: list<array{0: string, 1: string, 2: string, 3: string, 4?: bool, 5?: array{contra?: bool, line?: string}}>}>
     */
    public static function all(): array
    {
        return [...self::builtIn(), ...(array) config('accounting.chart_templates', [])];
    }

    /**
     * @return array{name: string, description: string, base: string, extras: list<array<int, mixed>>}|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, array{name: string, description: string, base: string, extras: list<array<int, mixed>>}>
     */
    private static function builtIn(): array
    {
        return [
            'general' => [
                'name' => 'General business',
                'description' => 'The standard chart: assets, liabilities, equity, income and expenses for any small or medium business.',
                'base' => 'general',
                'extras' => [],
            ],
            'trading' => [
                'name' => 'Trading and retail',
                'description' => 'Buying and selling goods: merchandise and goods in transit, sales returns and discounts, purchases, freight and customs, sales commission.',
                'base' => 'general',
                'extras' => [
                    ['1111', '1100', 'ASSET', 'Advances to Suppliers', false, ['line' => 'BS-RECEIVABLES']],
                    ['1112', '1100', 'ASSET', 'Cheques in Hand', false, ['line' => 'BS-CASH']],
                    ['1154', '1150', 'ASSET', 'Merchandise Inventory'],
                    ['1155', '1150', 'ASSET', 'Goods in Transit'],
                    ['2107', '2100', 'LIABILITY', 'Advances from Customers', false, ['line' => 'BS-DEFERRED-INCOME']],
                    ['2108', '2100', 'LIABILITY', 'Sales Tax Payable', false, ['line' => 'BS-TAX-LIABILITY']],
                    ['4107', '4100', 'INCOME', 'Sales Returns', false, ['contra' => true]],
                    ['4108', '4100', 'INCOME', 'Sales Discounts Allowed', false, ['contra' => true]],
                    ['5116', '5100', 'EXPENSE', 'Sales Commission'],
                    ['5117', '5100', 'EXPENSE', 'Advertising and Marketing'],
                    ['5118', '5100', 'EXPENSE', 'Carriage Outward'],
                    ['5209', '5200', 'EXPENSE', 'Purchases'],
                    ['5210', '5200', 'EXPENSE', 'Purchase Returns', false, ['contra' => true]],
                    ['5211', '5200', 'EXPENSE', 'Freight Inward'],
                    ['5212', '5200', 'EXPENSE', 'Customs Duty'],
                ],
            ],
            'manufacturing' => [
                'name' => 'Manufacturing',
                'description' => 'Production: raw materials, work in progress and finished goods, plant and machinery, a manufacturing overheads group and production variances.',
                'base' => 'general',
                'extras' => [
                    ['1155', '1150', 'ASSET', 'Work In Progress'],
                    ['1156', '1150', 'ASSET', 'Packing Materials'],
                    ['1157', '1150', 'ASSET', 'Spare Parts and Stores'],
                    ['1207', '1200', 'ASSET', 'Factory Building'],
                    ['1208', '1200', 'ASSET', 'Tools and Dies'],
                    ['2107', '2100', 'LIABILITY', 'Advances from Customers', false, ['line' => 'BS-DEFERRED-INCOME']],
                    ['5213', '5200', 'EXPENSE', 'Direct Materials Consumed'],
                    ['5300', '5000', 'EXPENSE', 'Manufacturing Overheads', true, ['line' => 'IS-COST-OF-SALES']],
                    ['5301', '5300', 'EXPENSE', 'Factory Rent'],
                    ['5302', '5300', 'EXPENSE', 'Factory Utilities'],
                    ['5303', '5300', 'EXPENSE', 'Factory Depreciation'],
                    ['5304', '5300', 'EXPENSE', 'Indirect Labour'],
                    ['5305', '5300', 'EXPENSE', 'Plant Repairs and Maintenance'],
                    ['5306', '5300', 'EXPENSE', 'Production Wastage'],
                    ['5307', '5300', 'EXPENSE', 'Material Price Variance'],
                    ['5308', '5300', 'EXPENSE', 'Production Variance'],
                ],
            ],
            'services' => [
                'name' => 'Professional services',
                'description' => 'Consulting, agencies and project work: unbilled revenue, retentions, consulting / retainer / project income, subcontractor costs.',
                'base' => 'general',
                'extras' => [
                    ['1111', '1100', 'ASSET', 'Unbilled Revenue', false, ['line' => 'BS-RECEIVABLES']],
                    ['1112', '1100', 'ASSET', 'Advances to Subcontractors', false, ['line' => 'BS-RECEIVABLES']],
                    ['2107', '2100', 'LIABILITY', 'Retention Payable'],
                    ['2108', '2100', 'LIABILITY', 'Accrued Subcontractor Costs'],
                    ['4107', '4100', 'INCOME', 'Consulting Fees'],
                    ['4108', '4100', 'INCOME', 'Retainer and Subscription Income'],
                    ['4109', '4100', 'INCOME', 'Project Income'],
                    ['5116', '5100', 'EXPENSE', 'Subcontractor Costs', false, ['line' => 'IS-COST-OF-SALES']],
                    ['5117', '5100', 'EXPENSE', 'Software and Subscriptions'],
                    ['5118', '5100', 'EXPENSE', 'Training and Development'],
                    ['5119', '5100', 'EXPENSE', 'Travel and Accommodation'],
                ],
            ],
            'school' => [
                'name' => 'School and education',
                'description' => 'The school chart: student fee receivable and the fee incomes, books, uniform and stationery inventories, plus hostel, annual charges and examination accounts.',
                'base' => 'school',
                'extras' => [
                    ['4107', '4100', 'INCOME', 'Hostel Fee Income'],
                    ['4108', '4100', 'INCOME', 'Admission Form Sales'],
                    ['4109', '4100', 'INCOME', 'Annual Charges Income'],
                    ['2107', '2100', 'LIABILITY', 'Scholarship Fund Payable'],
                    ['5116', '5100', 'EXPENSE', 'Teacher Training'],
                    ['5117', '5100', 'EXPENSE', 'Sports and Events'],
                    ['5118', '5100', 'EXPENSE', 'Examination Expenses'],
                ],
            ],
            'ngo' => [
                'name' => 'NGO and non-profit',
                'description' => 'Fund accounting: restricted, unrestricted and endowment funds, grants and pledges receivable, donations and grant income, programme and fundraising costs.',
                'base' => 'general',
                'extras' => [
                    ['1111', '1100', 'ASSET', 'Grants Receivable', false, ['line' => 'BS-RECEIVABLES']],
                    ['1112', '1100', 'ASSET', 'Pledges Receivable', false, ['line' => 'BS-RECEIVABLES']],
                    ['2107', '2100', 'LIABILITY', 'Grants Received in Advance', false, ['line' => 'BS-DEFERRED-INCOME']],
                    ['3106', '3100', 'EQUITY', 'Restricted Funds', false, ['line' => 'BS-RESERVES']],
                    ['3107', '3100', 'EQUITY', 'Unrestricted Funds', false, ['line' => 'BS-RESERVES']],
                    ['3108', '3100', 'EQUITY', 'Endowment Fund', false, ['line' => 'BS-RESERVES']],
                    ['4107', '4100', 'INCOME', 'Donations - General'],
                    ['4108', '4100', 'INCOME', 'Donations - Restricted'],
                    ['4109', '4100', 'INCOME', 'Grant Income'],
                    ['4110', '4100', 'INCOME', 'Charity and Zakat Receipts'],
                    ['5116', '5100', 'EXPENSE', 'Programme Delivery Costs'],
                    ['5117', '5100', 'EXPENSE', 'Beneficiary Support'],
                    ['5118', '5100', 'EXPENSE', 'Fundraising Costs'],
                    ['5119', '5100', 'EXPENSE', 'Volunteer Expenses'],
                    ['5120', '5100', 'EXPENSE', 'Monitoring and Evaluation'],
                ],
            ],
            'healthcare' => [
                'name' => 'Clinic and healthcare',
                'description' => 'Clinics, labs and hospitals: patient and insurance receivables, medical supplies and pharmacy stock, consultation, laboratory, pharmacy and procedure income.',
                'base' => 'general',
                'extras' => [
                    ['1111', '1100', 'ASSET', 'Patient Receivables', false, ['line' => 'BS-RECEIVABLES']],
                    ['1112', '1100', 'ASSET', 'Insurance Claims Receivable', false, ['line' => 'BS-RECEIVABLES']],
                    ['1154', '1150', 'ASSET', 'Medical Supplies Inventory'],
                    ['1155', '1150', 'ASSET', 'Pharmacy Inventory'],
                    ['1207', '1200', 'ASSET', 'Medical Equipment'],
                    ['2107', '2100', 'LIABILITY', 'Patient Deposits Payable'],
                    ['4107', '4100', 'INCOME', 'Consultation Fees'],
                    ['4108', '4100', 'INCOME', 'Laboratory Income'],
                    ['4109', '4100', 'INCOME', 'Pharmacy Sales'],
                    ['4110', '4100', 'INCOME', 'Inpatient Room Charges'],
                    ['4111', '4100', 'INCOME', 'Procedure and Surgery Income'],
                    ['5116', '5100', 'EXPENSE', 'Doctors Fees and Commission', false, ['line' => 'IS-COST-OF-SALES']],
                    ['5117', '5100', 'EXPENSE', 'Medical Consumables', false, ['line' => 'IS-COST-OF-SALES']],
                    ['5118', '5100', 'EXPENSE', 'Clinical Waste Disposal'],
                    ['5119', '5100', 'EXPENSE', 'Medical Equipment Maintenance'],
                ],
            ],
        ];
    }
}
