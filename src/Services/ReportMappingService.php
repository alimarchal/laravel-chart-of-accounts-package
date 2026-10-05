<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingChartOfAccountSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which statement line (and cash-flow class) each account reports under.
 *
 * An account uses its own line, else its nearest parent's: mapping a group maps everything under it. The cash-flow
 * class follows the same inheritance and defaults to the line's. Accounts that resolve to no line are reported on
 * an "unmapped" line of their section, so the statements always add up.
 */
class ReportMappingService
{
    /**
     * The mapping of the seeded chart (account code → line code). Override with config('accounting.reporting.recommended').
     */
    public const RECOMMENDED = [
        '1101' => 'BS-CASH', '1102' => 'BS-CASH',
        '1103' => 'BS-RECEIVABLES', '1104' => 'BS-RECEIVABLES', '1105' => 'BS-RECEIVABLES', '1106' => 'BS-RECEIVABLES',
        '1107' => 'BS-TAX-ASSET', '1150' => 'BS-INVENTORIES',
        '1200' => 'BS-PPE', '1206' => 'BS-DEPRECIATION', '1301' => 'BS-INVESTMENTS', '1302' => 'BS-PPE',
        '2100' => 'BS-PAYABLES', '2104' => 'BS-TAX-LIABILITY', '2106' => 'BS-DEFERRED-INCOME',
        '2201' => 'BS-SHORT-BORROWINGS', '2202' => 'BS-LONG-BORROWINGS',
        '3100' => 'BS-CAPITAL', '3105' => 'BS-RESERVES', '3101' => 'BS-RETAINED', '3102' => 'BS-RETAINED',
        '4100' => 'IS-REVENUE', '4200' => 'IS-OTHER-INCOME',
        '5100' => 'IS-ADMIN', '5113' => 'IS-FINANCE', '5114' => 'IS-DEPRECIATION',
        '5200' => 'IS-OTHER-EXPENSES', '5202' => 'IS-COST-OF-SALES', '5203' => 'IS-COST-OF-SALES', '5204' => 'IS-COST-OF-SALES',
    ];

    /** @var array<int, array{line_id: int|null, cash_flow_category: string|null, inherited: bool}>|null */
    private ?array $resolved = null;

    /**
     * @return Collection<int, ReportLine>
     */
    public function lines(?string $statement = null): Collection
    {
        return ReportLine::query()
            ->when($statement, fn ($query, string $value) => $query->where('statement', $value))
            ->orderBy('statement')->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /**
     * The line and cash-flow class every account resolves to (own value, else the nearest parent's; the class
     * defaults to the line's).
     *
     * @return array<int, array{line_id: int|null, cash_flow_category: string|null, inherited: bool}>
     */
    public function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $accounts = DB::table('accounting_chart_of_accounts')
            ->whereIn('company_id', CurrentCompany::ids())
            ->get(['id', 'parent_id', 'report_line_id', 'cash_flow_category'])
            ->keyBy('id');
        $lineCategories = DB::table('accounting_report_lines')
            ->whereIn('company_id', CurrentCompany::ids())
            ->pluck('cash_flow_category', 'id');
        $resolved = [];

        $find = function (int $id, string $column, int $depth = 0) use (&$find, $accounts): mixed {
            $account = $accounts->get($id);

            if ($account === null || $depth > 50) {
                return null;
            }

            return $account->{$column} ?? ($account->parent_id ? $find((int) $account->parent_id, $column, $depth + 1) : null);
        };

        foreach ($accounts as $id => $account) {
            $lineId = $find((int) $id, 'report_line_id');
            $category = $find((int) $id, 'cash_flow_category') ?? ($lineId ? $lineCategories->get($lineId) : null);

            $resolved[(int) $id] = [
                'line_id' => $lineId === null ? null : (int) $lineId,
                'cash_flow_category' => $category === null ? null : (string) $category,
                'inherited' => $account->report_line_id === null && $lineId !== null,
            ];
        }

        return $this->resolved = $resolved;
    }

    /**
     * Map an account (and so its sub-accounts) to a line and, for balance sheet accounts, a cash-flow class.
     * Null clears the account's own value: it inherits again.
     */
    public function setMapping(ChartOfAccount $account, ?int $lineId, ?string $cashFlowCategory): ChartOfAccount
    {
        $statement = $this->statementOf($account);
        $line = $lineId === null ? null : ReportLine::query()->find($lineId);

        if ($lineId !== null && $line === null) {
            throw new AccountingException('The report line does not exist.');
        }

        if ($line && $line->statement !== $statement) {
            throw new AccountingException(sprintf('%s is a %s account: map it to a %s line.', $account->account_code, $this->statementLabel($statement), $this->statementLabel($statement)));
        }

        if ($cashFlowCategory !== null && ! array_key_exists($cashFlowCategory, ReportLine::CASH_FLOW)) {
            throw new AccountingException("Unknown cash-flow class \"{$cashFlowCategory}\".");
        }

        if ($cashFlowCategory !== null && $statement !== ReportLine::BALANCE_SHEET) {
            throw new AccountingException('Only balance sheet accounts have a cash-flow class: income and expenses are in the profit for the period.');
        }

        $old = ['report_line_id' => $account->getAttributes()['report_line_id'] ?? null, 'cash_flow_category' => $account->getAttributes()['cash_flow_category'] ?? null];
        $new = ['report_line_id' => $line?->id, 'cash_flow_category' => $cashFlowCategory];

        if ($old != $new) {
            $account->forceFill($new)->save();
            AccountingAuditLog::record($account, 'REPORT_MAPPING_CHANGED', $old, $new, ['line' => $line?->code]);
            $this->resolved = null;
        }

        return $account;
    }

    /**
     * Map the seeded accounts to their standard lines. Accounts that already have a line are left alone.
     * With $onlySeeded, an account is mapped only while its name is still the seeded one (used by the seeder,
     * so a chart that reuses the codes for other things is not touched).
     *
     * @return list<array{account_code: string, account_name: string, line: string}>
     */
    public function applyRecommended(bool $onlySeeded = false): array
    {
        $recommended = (array) config('accounting.reporting.recommended', self::RECOMMENDED);
        $lines = ReportLine::query()->get()->keyBy('code');
        $seededNames = $onlySeeded ? $this->seededNames() : [];
        $applied = [];

        DB::transaction(function () use ($recommended, $lines, $seededNames, $onlySeeded, &$applied): void {
            foreach (ChartOfAccount::query()->whereIn('account_code', array_keys($recommended))->whereNull('report_line_id')->get() as $account) {
                $line = $lines->get($recommended[$account->account_code]);

                if ($line === null || $line->statement !== $this->statementOf($account)) {
                    continue;
                }

                if ($onlySeeded && ($seededNames[$account->account_code] ?? null) !== $account->account_name) {
                    continue;
                }

                $account->forceFill(['report_line_id' => $line->id])->save();
                $applied[] = ['account_code' => $account->account_code, 'account_name' => $account->account_name, 'line' => $line->code];
            }
        });

        if ($applied !== [] && ! $onlySeeded) {
            AccountingAuditLog::record((new ChartOfAccount)->forceFill(['id' => 0]), 'REPORT_MAPPING_RECOMMENDED', null, null, ['mapped' => $applied]);
        }

        $this->resolved = null;

        return $applied;
    }

    /**
     * Posting accounts that resolve to no line (they show on an "unmapped" line of their section).
     *
     * @return Collection<int, ChartOfAccount>
     */
    public function unmapped(): Collection
    {
        $resolved = $this->resolve();

        return ChartOfAccount::query()->where('is_group', false)->orderBy('account_code')->get()
            ->filter(fn (ChartOfAccount $account) => ($resolved[$account->id]['line_id'] ?? null) === null)
            ->values();
    }

    /**
     * Seed the standard lines of the current company (existing lines are kept).
     */
    public function seedLines(): void
    {
        foreach (ReportLine::defaults() as $line) {
            ReportLine::query()->firstOrCreate(['code' => $line['code']], [...$line, 'is_system' => true]);
        }
    }

    /**
     * @param  array<string, mixed>  $data  statement, code, name, section, cash_flow_category, sort_order
     */
    public function saveLine(array $data, ?ReportLine $line = null): ReportLine
    {
        $statement = $line->statement ?? ($data['statement'] ?? null);

        if (! array_key_exists((string) $statement, ReportLine::SECTIONS)) {
            throw new AccountingException('The statement must be balance_sheet or income_statement.');
        }

        if (isset($data['section']) && ! array_key_exists($data['section'], ReportLine::SECTIONS[$statement])) {
            throw new AccountingException('Choose a section of the '.$this->statementLabel($statement).'.');
        }

        if (array_key_exists('cash_flow_category', $data) && $data['cash_flow_category'] !== null) {
            if ($statement !== ReportLine::BALANCE_SHEET || ! array_key_exists($data['cash_flow_category'], ReportLine::CASH_FLOW)) {
                throw new AccountingException('Only balance sheet lines have a cash-flow class (cash, operating, non_cash, investing, financing).');
            }
        }

        $code = isset($data['code']) ? strtoupper(trim((string) $data['code'])) : null;

        if ($code !== null && ReportLine::query()->where('code', $code)->whereKeyNot($line?->id)->exists()) {
            throw new AccountingException("The code {$code} is already used by another line.");
        }

        if ($line && $line->is_system && $code !== null && $code !== $line->code) {
            throw new AccountingException('The code of a standard line cannot change (rename it instead).');
        }

        $attributes = array_filter([
            'statement' => $line ? null : $statement,
            'code' => $code,
            'name' => $data['name'] ?? null,
            'section' => $data['section'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($line ? null : ((int) ReportLine::query()->where('statement', $statement)->max('sort_order') + 10)),
        ], fn ($value) => $value !== null);

        if (array_key_exists('cash_flow_category', $data)) {
            $attributes['cash_flow_category'] = $data['cash_flow_category'];
        }

        $line ??= new ReportLine;
        $before = $line->exists ? $line->only(array_keys($attributes)) : null;
        $line->fill($attributes);

        if (! $line->exists && (empty($line->code) || empty($line->name) || empty($line->section))) {
            throw new AccountingException('A line needs a code, a name and a section.');
        }

        $line->save();
        AccountingAuditLog::record($line, $before === null ? 'REPORT_LINE_CREATED' : 'REPORT_LINE_UPDATED', $before, $line->only(array_keys($attributes)));
        $this->resolved = null;

        return $line;
    }

    public function deleteLine(ReportLine $line): void
    {
        if ($line->is_system) {
            throw new AccountingException('Standard lines cannot be deleted; rename or reorder them instead.');
        }

        if (ChartOfAccount::query()->where('report_line_id', $line->id)->exists()) {
            throw new AccountingException('Accounts are mapped to this line: map them elsewhere first.');
        }

        AccountingAuditLog::record($line, 'REPORT_LINE_DELETED', $line->only(['code', 'name', 'section']), null);
        $line->delete();
        $this->resolved = null;
    }

    /**
     * The statement an account reports on.
     */
    public function statementOf(ChartOfAccount $account): string
    {
        $group = AccountType::query()->whereKey($account->account_type_id)->value('report_group');

        return $group === 'IncomeStatement' ? ReportLine::INCOME_STATEMENT : ReportLine::BALANCE_SHEET;
    }

    private function statementLabel(string $statement): string
    {
        return $statement === ReportLine::INCOME_STATEMENT ? 'income statement' : 'balance sheet';
    }

    /**
     * @return array<string, string> code → seeded name
     */
    private function seededNames(): array
    {
        $names = [];

        foreach (app(AccountingChartOfAccountSeeder::class)->accounts() as $account) {
            $names[$account['account_code']] = $account['account_name'];
        }

        return $names;
    }
}
