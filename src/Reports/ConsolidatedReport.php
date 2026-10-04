<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;

/**
 * Group reports: each company's report is produced exactly as it would be for that company, then
 * the rows are combined by account code with one column per company and a group total. All
 * companies share the base currency, so amounts add up directly. Intercompany balances are not
 * eliminated (post elimination entries in a consolidation company if you need them).
 */
class ConsolidatedReport
{
    public const REPORTS = ['trial-balance', 'balance-sheet', 'income-statement'];

    public function __construct(private readonly CurrentCompany $companies) {}

    /**
     * @param  Collection<int, Company>  $companies
     * @param  array<string, mixed>  $filters
     * @return array{report: string, companies: array<int, array{id: int, code: string, name: string}>, data: array<int, array<string, mixed>>, totals: array<string, mixed>, filters: array<string, mixed>}
     */
    public function build(string $report, Collection $companies, array $filters = []): array
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);

        $includeZero = (bool) ($filters['include_zero'] ?? false);

        if ($report === 'income-statement') {
            // One date range for every company (the default range is the current company's fiscal year).
            [$from, $to] = app(IncomeStatementReport::class)->range($filters);
            $filters = ['date_from' => $from, 'date_to' => $to];
        } elseif ($report === 'balance-sheet') {
            $filters = ['as_of_date' => $filters['as_of_date'] ?? now()->toDateString()];
        } else {
            $filters = [];
        }

        $filters['include_zero'] = $includeZero;

        $perCompany = $companies->mapWithKeys(fn (Company $company) => [
            $company->code => $this->companies->runAs($company, fn () => $this->companyReport($report, $filters)),
        ]);

        $rows = [];

        foreach ($perCompany as $code => $result) {
            foreach ($result['rows'] as $row) {
                $key = $row->account_code !== '' ? $row->account_code : $row->account_name;
                $rows[$key] ??= [
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'account_type' => $row->account_type ?? null,
                    'normal_balance' => $row->normal_balance ?? null,
                    'companies' => [],
                    'balance_cents' => 0,
                ];
                $cents = Money::toCents((string) $row->balance);
                $rows[$key]['companies'][$code] = ($rows[$key]['companies'][$code] ?? 0) + $cents;
                $rows[$key]['balance_cents'] += $cents;
            }
        }

        if (! ($filters['include_zero'] ?? false)) {
            $rows = array_filter($rows, fn (array $row) => $row['balance_cents'] !== 0 || array_filter($row['companies']) !== []);
        }

        ksort($rows, SORT_NATURAL);

        /** @var array<string, array<string, mixed>> $rows */
        $data = array_values(array_map(function (array $row) use ($perCompany): array {
            $row['companies'] = $perCompany->keys()
                ->mapWithKeys(fn (string $code) => [$code => Money::fromCents($row['companies'][$code] ?? 0)])
                ->all();

            $row['balance'] = Money::fromCents($row['balance_cents']);
            unset($row['balance_cents']);

            return $row;
        }, $rows));

        return [
            'report' => $report,
            'companies' => $companies->map(fn (Company $company) => ['id' => (int) $company->getKey(), 'code' => $company->code, 'name' => $company->name])->values()->all(),
            'data' => $data,
            'totals' => [
                'companies' => $perCompany->map(fn (array $result) => $result['totals'])->all(),
                'group' => $this->sumTotals($perCompany->pluck('totals')),
            ],
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: Collection<int, object>, totals: array<string, string>}
     */
    private function companyReport(string $report, array $filters): array
    {
        if ($report === 'trial-balance') {
            $tb = app(TrialBalanceReport::class);
            $rows = $tb->rows();
            $totals = $tb->totals($rows);

            return ['rows' => $rows, 'totals' => array_map(fn ($value) => Money::fromCents(Money::toCents($value)), $totals)];
        }

        if ($report === 'balance-sheet') {
            $bs = app(BalanceSheetReport::class);
            $rows = $bs->rows($filters);
            $totals = $bs->totals($filters, $rows);

            return ['rows' => $rows, 'totals' => array_map(fn ($value) => Money::fromCents(Money::toCents($value)), $totals)];
        }

        $rows = app(IncomeStatementReport::class)->rows($filters);
        $revenue = $rows->where('normal_balance', 'credit')->sum(fn ($row) => Money::toCents((string) $row->balance));
        $expenses = $rows->where('normal_balance', 'debit')->sum(fn ($row) => Money::toCents((string) $row->balance));

        return ['rows' => $rows, 'totals' => [
            'revenue' => Money::fromCents($revenue),
            'expenses' => Money::fromCents($expenses),
            'net_income' => Money::fromCents($revenue - $expenses),
        ]];
    }

    /**
     * @param  Collection<int, array<string, string>>  $totals
     * @return array<string, string>
     */
    private function sumTotals(Collection $totals): array
    {
        $sum = [];

        foreach ($totals as $companyTotals) {
            foreach ($companyTotals as $key => $value) {
                $sum[$key] = ($sum[$key] ?? 0) + Money::toCents($value);
            }
        }

        return array_map(fn (int $cents) => Money::fromCents($cents), $sum);
    }
}
