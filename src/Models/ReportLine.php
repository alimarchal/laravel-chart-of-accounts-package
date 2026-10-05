<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A line of the financial statements ("Trade and other receivables", "Cost of sales" …). Accounts map to a line
 * (directly or through a parent account); the line's section places it on the statement and its cash-flow class
 * places its movements in the indirect cash flow.
 *
 * @property int $id
 * @property string $statement
 * @property string $code
 * @property string $name
 * @property string $section
 * @property string|null $cash_flow_category
 * @property int $sort_order
 * @property bool $is_system
 */
class ReportLine extends AccountingModel
{
    use BelongsToCompany;

    public const BALANCE_SHEET = 'balance_sheet';

    public const INCOME_STATEMENT = 'income_statement';

    /** Sections in statement order. */
    public const SECTIONS = [
        self::BALANCE_SHEET => [
            'current_assets' => 'Current assets',
            'non_current_assets' => 'Non-current assets',
            'current_liabilities' => 'Current liabilities',
            'non_current_liabilities' => 'Non-current liabilities',
            'equity' => 'Equity',
        ],
        self::INCOME_STATEMENT => [
            'revenue' => 'Revenue',
            'cost_of_sales' => 'Cost of sales',
            'other_income' => 'Other income',
            'operating_expenses' => 'Operating expenses',
            'finance_costs' => 'Finance costs',
            'income_tax' => 'Income tax',
        ],
    ];

    /**
     * Cash-flow classes of balance sheet accounts (indirect method). Income statement accounts are in profit.
     */
    public const CASH_FLOW = [
        'cash' => 'Cash and cash equivalents',
        'operating' => 'Operating — working capital',
        'non_cash' => 'Operating — non-cash adjustment',
        'investing' => 'Investing',
        'financing' => 'Financing',
    ];

    protected $table = 'accounting_report_lines';

    protected $fillable = ['statement', 'code', 'name', 'section', 'cash_flow_category', 'sort_order', 'is_system'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_system' => 'boolean'];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'report_line_id');
    }

    /**
     * The standard layout seeded for every company (IFRS-style captions).
     *
     * @return list<array{statement: string, code: string, name: string, section: string, cash_flow_category: string|null, sort_order: int}>
     */
    public static function defaults(): array
    {
        $lines = [
            [self::BALANCE_SHEET, 'BS-CASH', 'Cash and cash equivalents', 'current_assets', 'cash'],
            [self::BALANCE_SHEET, 'BS-RECEIVABLES', 'Trade and other receivables', 'current_assets', 'operating'],
            [self::BALANCE_SHEET, 'BS-INVENTORIES', 'Inventories', 'current_assets', 'operating'],
            [self::BALANCE_SHEET, 'BS-TAX-ASSET', 'Tax receivable', 'current_assets', 'operating'],
            [self::BALANCE_SHEET, 'BS-PPE', 'Property, plant and equipment', 'non_current_assets', 'investing'],
            [self::BALANCE_SHEET, 'BS-DEPRECIATION', 'Accumulated depreciation', 'non_current_assets', 'non_cash'],
            [self::BALANCE_SHEET, 'BS-INVESTMENTS', 'Long-term investments', 'non_current_assets', 'investing'],
            [self::BALANCE_SHEET, 'BS-PAYABLES', 'Trade and other payables', 'current_liabilities', 'operating'],
            [self::BALANCE_SHEET, 'BS-TAX-LIABILITY', 'Tax payable', 'current_liabilities', 'operating'],
            [self::BALANCE_SHEET, 'BS-DEFERRED-INCOME', 'Deferred income', 'current_liabilities', 'operating'],
            [self::BALANCE_SHEET, 'BS-SHORT-BORROWINGS', 'Short-term borrowings', 'current_liabilities', 'financing'],
            [self::BALANCE_SHEET, 'BS-LONG-BORROWINGS', 'Long-term borrowings', 'non_current_liabilities', 'financing'],
            [self::BALANCE_SHEET, 'BS-CAPITAL', 'Share capital', 'equity', 'financing'],
            [self::BALANCE_SHEET, 'BS-RESERVES', 'Reserves', 'equity', 'non_cash'],
            [self::BALANCE_SHEET, 'BS-RETAINED', 'Retained earnings', 'equity', 'financing'],
            [self::INCOME_STATEMENT, 'IS-REVENUE', 'Revenue', 'revenue', null],
            [self::INCOME_STATEMENT, 'IS-COST-OF-SALES', 'Cost of sales', 'cost_of_sales', null],
            [self::INCOME_STATEMENT, 'IS-OTHER-INCOME', 'Other income', 'other_income', null],
            [self::INCOME_STATEMENT, 'IS-ADMIN', 'Administrative expenses', 'operating_expenses', null],
            [self::INCOME_STATEMENT, 'IS-DEPRECIATION', 'Depreciation', 'operating_expenses', null],
            [self::INCOME_STATEMENT, 'IS-OTHER-EXPENSES', 'Other operating expenses', 'operating_expenses', null],
            [self::INCOME_STATEMENT, 'IS-FINANCE', 'Finance costs', 'finance_costs', null],
            [self::INCOME_STATEMENT, 'IS-TAX', 'Income tax', 'income_tax', null],
        ];

        return array_map(fn (array $line, int $index) => [
            'statement' => $line[0], 'code' => $line[1], 'name' => $line[2], 'section' => $line[3],
            'cash_flow_category' => $line[4], 'sort_order' => ($index + 1) * 10,
        ], $lines, array_keys($lines));
    }
}
