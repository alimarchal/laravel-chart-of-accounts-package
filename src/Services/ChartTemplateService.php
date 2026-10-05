<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingChartOfAccountSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Alimarchal\LaravelChartOfAccounts\Support\ChartTemplates;
use Illuminate\Support\Facades\DB;

/**
 * Applies an industry chart template (see ChartTemplates) to the current company: the accounts it has and the
 * company lacks are added, parents first, and mapped to their statement lines. Accounts that already exist are
 * never changed — not renamed, moved or re-typed — so a template can be applied to a chart in use.
 */
class ChartTemplateService
{
    /**
     * @return list<array{key: string, name: string, description: string, base: string, accounts: int, extras: int}>
     */
    public function available(): array
    {
        $list = [];

        foreach (ChartTemplates::all() as $key => $template) {
            $list[] = [
                'key' => (string) $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'base' => $template['base'],
                'accounts' => count($this->rows($template)),
                'extras' => count($template['extras']),
            ];
        }

        return $list;
    }

    /**
     * What applying the template would do, account by account.
     *
     * @return array{template: array{key: string, name: string, description: string}, rows: list<array{account_code: string, account_name: string, parent_code: string|null, type: string, is_group: bool, extra: bool, status: string, existing_name: string|null, line: string|null}>, summary: array{new: int, exists: int, different: int, blocked: int}}
     */
    public function preview(string $key): array
    {
        $template = $this->template($key);
        $existing = ChartOfAccount::query()->pluck('account_name', 'account_code')->all();
        $rows = $this->rows($template);
        $codes = array_flip(array_column($rows, 'account_code'));
        $summary = ['new' => 0, 'exists' => 0, 'different' => 0, 'blocked' => 0];
        $out = [];

        foreach ($rows as $row) {
            $current = $existing[$row['account_code']] ?? null;
            $status = match (true) {
                $current !== null && $current === $row['account_name'] => 'exists',
                $current !== null => 'different',
                $row['parent_code'] !== null && ! isset($existing[$row['parent_code']]) && ! isset($codes[$row['parent_code']]) => 'blocked',
                default => 'new',
            };
            $summary[$status]++;
            $out[] = [
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'parent_code' => $row['parent_code'],
                'type' => $row['type_code'],
                'is_group' => $row['is_group'],
                'extra' => $row['extra'],
                'status' => $status,
                'existing_name' => $status === 'different' ? $current : null,
                'line' => $this->lineCode($row),
            ];
        }

        return ['template' => ['key' => $key, 'name' => $template['name'], 'description' => $template['description']], 'rows' => $out, 'summary' => $summary];
    }

    /**
     * @return array{created: list<string>, skipped: int}
     */
    public function apply(string $key): array
    {
        $template = $this->template($key);
        $rows = $this->rows($template);

        if ($rows === []) {
            return ['created' => [], 'skipped' => 0];
        }

        return DB::transaction(function () use ($key, $rows): array {
            $types = AccountType::query()->get()->keyBy('code');
            $currency = Currency::query()->where('is_base', true)->first();

            if ($currency === null) {
                throw new AccountingException('There is no base currency yet: run accounting:seed first.');
            }

            $lines = ReportLine::query()->get()->keyBy('code');
            $ids = ChartOfAccount::query()->pluck('id', 'account_code')->all();
            $created = [];
            $skipped = 0;

            foreach ($this->parentsFirst($rows) as $row) {
                if (isset($ids[$row['account_code']])) {
                    $skipped++;

                    continue;
                }

                $type = $types->get($row['type_code']);
                $parentId = $row['parent_code'] === null ? null : ($ids[$row['parent_code']] ?? null);

                if ($type === null || ($row['parent_code'] !== null && $parentId === null)) {
                    $skipped++;

                    continue;
                }

                $lineCode = $this->lineCode($row);
                $line = $lineCode ? $lines->get($lineCode) : null;
                $account = new ChartOfAccount([
                    'account_code' => $row['account_code'],
                    'parent_id' => $parentId,
                    'account_type_id' => $type->id,
                    'currency_id' => $currency->id,
                    'account_name' => $row['account_name'],
                    'normal_balance' => $row['contra'] ? ($type->normal_balance === 'debit' ? 'credit' : 'debit') : $type->normal_balance,
                    'is_group' => $row['is_group'],
                    'is_active' => true,
                    'is_system' => ! $row['extra'],
                ]);

                if ($line !== null && ($line->statement === ReportLine::INCOME_STATEMENT) === ($type->report_group === 'IncomeStatement')) {
                    $account->forceFill(['report_line_id' => $line->id]);
                }

                $account->save();
                $ids[$row['account_code']] = $account->id;
                $created[] = $row['account_code'];
            }

            if ($created !== []) {
                AccountingAuditLog::record((new ChartOfAccount)->forceFill(['id' => 0]), 'CHART_TEMPLATE_APPLIED', null, null, ['template' => $key, 'created' => $created]);
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /**
     * @return array{name: string, description: string, base: string, extras: list<array<int, mixed>>}
     */
    private function template(string $key): array
    {
        return ChartTemplates::find($key) ?? throw new AccountingException("Unknown chart template \"{$key}\".");
    }

    /**
     * The base chart and the template's extras as one list. An extra whose code the base chart already uses is
     * dropped (the base wins).
     *
     * @param  array{name: string, description: string, base: string, extras: list<array<int, mixed>>}  $template
     * @return list<array{account_code: string, account_name: string, parent_code: string|null, type_code: string, is_group: bool, contra: bool, extra: bool, line: string|null}>
     */
    private function rows(array $template): array
    {
        $rows = [];

        foreach (app(AccountingChartOfAccountSeeder::class)->accounts($template['base']) as $account) {
            $type = AccountType::query()->where('code', $account['type_code'])->first();

            $rows[(string) $account['account_code']] = [
                'account_code' => (string) $account['account_code'],
                'account_name' => (string) $account['account_name'],
                'parent_code' => $account['parent_code'] === null ? null : (string) $account['parent_code'],
                'type_code' => (string) $account['type_code'],
                'is_group' => (bool) $account['is_group'],
                'contra' => $type !== null && $type->normal_balance !== $account['normal_balance'],
                'extra' => false,
                'line' => null,
            ];
        }

        foreach ($template['extras'] as $extra) {
            $code = (string) $extra[0];

            if (isset($rows[$code])) {
                continue;
            }

            $rows[$code] = [
                'account_code' => $code,
                'account_name' => (string) $extra[3],
                'parent_code' => (string) $extra[1] === '' ? null : (string) $extra[1],
                'type_code' => (string) $extra[2],
                'is_group' => (bool) ($extra[4] ?? false),
                'contra' => (bool) ($extra[5]['contra'] ?? false),
                'extra' => true,
                'line' => $extra[5]['line'] ?? null,
            ];
        }

        return array_values($rows);
    }

    /**
     * The statement line an account is mapped to: its own, else the recommended mapping of its code.
     *
     * @param  array{account_code: string, line: string|null}  $row
     */
    private function lineCode(array $row): ?string
    {
        return $row['line'] ?? ((array) config('accounting.reporting.recommended', ReportMappingService::RECOMMENDED))[$row['account_code']] ?? null;
    }

    /**
     * @param  list<array{account_code: string, account_name: string, parent_code: string|null, type_code: string, is_group: bool, contra: bool, extra: bool, line: string|null}>  $rows
     * @return list<array{account_code: string, account_name: string, parent_code: string|null, type_code: string, is_group: bool, contra: bool, extra: bool, line: string|null}>
     */
    private function parentsFirst(array $rows): array
    {
        $byCode = [];

        foreach ($rows as $row) {
            $byCode[$row['account_code']] = $row;
        }

        $depth = function (string $code) use (&$depth, $byCode): int {
            $parent = $byCode[$code]['parent_code'] ?? null;

            return $parent !== null && isset($byCode[$parent]) ? 1 + $depth($parent) : 0;
        };
        usort($rows, fn (array $a, array $b) => $depth($a['account_code']) <=> $depth($b['account_code']));

        return $rows;
    }
}
