<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\SpreadsheetReader;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imports a chart of accounts from rows (see SpreadsheetReader) and exports the chart in the same layout,
 * so an export can be edited and imported back.
 *
 * Every row goes through ChartOfAccountService (and the database guards), inside one transaction: a
 * preview runs the real import and rolls it back, so what the preview reports is exactly what an import
 * does. An import with any row in error changes nothing.
 */
class ChartOfAccountImportService
{
    /** The columns of the import file, in export order. */
    public const COLUMNS = ['account_code', 'account_name', 'parent_code', 'account_type', 'normal_balance', 'currency', 'is_group', 'is_active', 'control_type', 'description'];

    private const ALIASES = [
        'code' => 'account_code', 'account_no' => 'account_code', 'account_number' => 'account_code',
        'name' => 'account_name', 'title' => 'account_name',
        'parent' => 'parent_code', 'parent_account' => 'parent_code', 'parent_account_code' => 'parent_code',
        'type' => 'account_type', 'account_type_code' => 'account_type',
        'currency_code' => 'currency', 'group' => 'is_group', 'active' => 'is_active',
    ];

    public function __construct(private readonly ChartOfAccountService $accounts) {}

    /**
     * The chart in import layout (the import orders parents before children itself).
     *
     * @return Collection<int, array<string, string>>
     */
    public function exportRows(): Collection
    {
        $accounts = ChartOfAccount::query()->with(['accountType:id,code', 'currency:id,code', 'parent:id,account_code'])->orderBy('account_code')->get();

        return collect($this->exportList($accounts->all()));
    }

    /**
     * A starter file: the columns with two example rows.
     *
     * @return Collection<int, array<string, string>>
     */
    public function templateRows(): Collection
    {
        return collect($this->templateList());
    }

    /**
     * @param  array<int, ChartOfAccount>  $accounts
     * @return list<array<string, string>>
     */
    private function exportList(array $accounts): array
    {
        $rows = [];

        foreach ($accounts as $account) {
            $parent = $account->parent;
            $currency = $account->currency;

            $rows[] = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'parent_code' => $parent instanceof ChartOfAccount ? $parent->account_code : '',
                'account_type' => (string) $account->accountType->code,
                'normal_balance' => (string) $account->normal_balance,
                'currency' => $currency instanceof Currency ? $currency->code : '',
                'is_group' => $account->is_group ? 'yes' : 'no',
                'is_active' => $account->is_active ? 'yes' : 'no',
                'control_type' => (string) ($account->getAttributes()['control_type'] ?? ''),
                'description' => (string) $account->description,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function templateList(): array
    {
        $expense = (string) (AccountType::query()->where('code', 'EXPENSE')->value('code')
            ?? AccountType::query()->where('report_group', 'IncomeStatement')->where('normal_balance', 'debit')->orderBy('id')->value('code'));
        $currency = (string) Currency::query()->where('is_base', true)->value('code');

        return [
            ['account_code' => '6000', 'account_name' => 'Marketing Expenses', 'parent_code' => '', 'account_type' => $expense, 'normal_balance' => '', 'currency' => $currency, 'is_group' => 'yes', 'is_active' => 'yes', 'control_type' => '', 'description' => 'Group: holds the accounts below'],
            ['account_code' => '6001', 'account_name' => 'Online Advertising', 'parent_code' => '6000', 'account_type' => '', 'normal_balance' => '', 'currency' => '', 'is_group' => 'no', 'is_active' => 'yes', 'control_type' => '', 'description' => 'Type and currency default to the parent / base currency'],
        ];
    }

    /**
     * Read an uploaded CSV or XLSX file.
     *
     * @return list<array<string, string>>
     */
    public function readUpload(UploadedFile $file): array
    {
        return SpreadsheetReader::read((string) $file->getRealPath(), $file->getClientOriginalExtension(), (int) config('accounting.chart_import.max_rows', 5000));
    }

    /**
     * Keep previewed rows for the confirming request (30 minutes, per user), so the file is uploaded once.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<string, mixed>  $result  the preview
     */
    public function stash(array $rows, string $mode, string $filename, array $result): string
    {
        $token = Str::random(40);
        Cache::put($this->stashKey($token), ['rows' => $rows, 'mode' => $mode, 'filename' => $filename, 'result' => $result, 'company_id' => CurrentCompany::currentId()], now()->addMinutes(30));

        return $token;
    }

    /**
     * @return array{rows: list<array<string, string>>, mode: string, filename: string, result: array{committed: bool, rows: list<array<string, mixed>>, summary: array{create: int, update: int, unchanged: int, error: int}}, company_id: int|null}|null
     */
    public function stashed(string $token): ?array
    {
        $stash = Cache::get($this->stashKey($token));

        // A preview made in another company is not imported into this one.
        return is_array($stash) && $stash['company_id'] === CurrentCompany::currentId() ? $stash : null;
    }

    public function forget(string $token): void
    {
        Cache::forget($this->stashKey($token));
    }

    private function stashKey(string $token): string
    {
        return 'accounting:chart-import:'.Auth::id().':'.preg_replace('/[^A-Za-z0-9]/', '', $token);
    }

    /**
     * Run the import. With $commit false nothing is saved (a preview).
     *
     * @param  list<array<string, string>>  $rows
     * @param  string  $mode  upsert updates existing codes; create leaves them unchanged
     * @return array{committed: bool, rows: list<array{line: int, account_code: string, account_name: string, action: string, changes: array<string, array{0: mixed, 1: mixed}>, errors: list<string>}>, summary: array{create: int, update: int, unchanged: int, error: int}}
     */
    public function run(array $rows, bool $commit, string $mode = 'upsert'): array
    {
        if (! in_array($mode, ['upsert', 'create'], true)) {
            throw new AccountingException('The import mode must be "upsert" or "create".');
        }

        if ($rows === []) {
            throw new AccountingException('The file has no accounts.');
        }

        $rows = array_map(fn (array $row) => $this->normalise($row), $rows);
        $results = [];
        $order = $this->order($rows, $results);

        DB::beginTransaction();

        try {
            foreach ($order as $index) {
                $results[$index] = $this->apply($index + 2, $rows[$index], $mode);
            }

            ksort($results);
            $results = array_values($results);
            $summary = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'error' => 0];

            foreach ($results as $result) {
                $summary[$result['action']]++;
            }

            $committed = $commit && $summary['error'] === 0 && ($summary['create'] + $summary['update']) > 0;

            if ($committed) {
                // One summary record for the whole file (record 0); each account change is audited on its own too.
                AccountingAuditLog::record((new ChartOfAccount)->forceFill(['id' => 0]), 'CHART_IMPORTED', null, null, [
                    'mode' => $mode,
                    'summary' => $summary,
                    'created' => collect($results)->where('action', 'create')->pluck('account_code')->all(),
                    'updated' => collect($results)->where('action', 'update')->pluck('account_code')->all(),
                ]);
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return ['committed' => $committed, 'rows' => $results, 'summary' => $summary];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalise(array $row): array
    {
        $clean = [];

        foreach ($row as $key => $value) {
            $clean[self::ALIASES[$key] ?? $key] = trim((string) $value);
        }

        return $clean;
    }

    /**
     * Rows in an order where every parent named in the file comes before its children. Rows whose
     * parent chain loops get an error result instead.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<int, array<string, mixed>>  $results
     * @return list<int>
     */
    private function order(array $rows, array &$results): array
    {
        $byCode = [];

        foreach ($rows as $index => $row) {
            $code = mb_strtolower($row['account_code'] ?? '');

            if ($code !== '' && isset($byCode[$code])) {
                $results[$index] = $this->failure($index + 2, $row, ["Account code {$row['account_code']} appears more than once in the file (first on line ".($byCode[$code] + 2).').']);

                continue;
            }

            if ($code !== '') {
                $byCode[$code] = $index;
            }
        }

        $depth = [];
        $depthOf = function (int $index, array $seen = []) use (&$depthOf, &$depth, $rows, $byCode): ?int {
            if (isset($depth[$index])) {
                return $depth[$index];
            }

            $parent = mb_strtolower($rows[$index]['parent_code'] ?? '');

            if ($parent === '' || ! isset($byCode[$parent])) {
                return $depth[$index] = 0;
            }

            if (in_array($index, $seen, true)) {
                return null;
            }

            $parentDepth = $depthOf($byCode[$parent], [...$seen, $index]);

            return $depth[$index] = $parentDepth === null ? null : $parentDepth + 1;
        };

        $order = [];

        foreach (array_keys($rows) as $index) {
            if (isset($results[$index])) {
                continue;
            }

            $level = $depthOf($index);

            if ($level === null) {
                $results[$index] = $this->failure($index + 2, $rows[$index], ['The parent accounts in the file form a loop.']);

                continue;
            }

            $order[$index] = $level;
        }

        asort($order, SORT_NUMERIC);

        return array_keys($order);
    }

    /**
     * @param  array<string, string>  $row
     * @return array{line: int, account_code: string, account_name: string, action: string, changes: array<string, array{0: mixed, 1: mixed}>, errors: list<string>}
     */
    private function apply(int $line, array $row, string $mode): array
    {
        $code = $row['account_code'] ?? '';

        if ($code === '') {
            return $this->failure($line, $row, ['The account code is required.']);
        }

        $existing = ChartOfAccount::query()->where('account_code', $code)->first();

        if ($existing && $mode === 'create') {
            return $this->result($line, $existing->account_code, $existing->account_name, 'unchanged', [], []);
        }

        $errors = [];
        $data = $this->attributes($row, $existing, $errors);

        if ($errors !== []) {
            return $this->failure($line, $row, $errors);
        }

        $changes = [];

        foreach ($data as $field => $value) {
            $old = $existing ? ($existing->getAttributes()[$field] ?? null) : null;

            if (! $existing || (string) $this->comparable($old) !== (string) $this->comparable($value)) {
                $changes[$this->label($field)] = [$existing ? $this->display($field, $old) : null, $this->display($field, $value)];
            }
        }

        if ($existing && $changes === []) {
            return $this->result($line, $existing->account_code, $existing->account_name, 'unchanged', [], []);
        }

        try {
            DB::transaction(function () use ($data, $existing): void {
                // A row may change only some fields: validate them together with the account's current values.
                $current = $existing ? collect($existing->getAttributes())->only(['parent_id', 'account_type_id', 'currency_id', 'account_code', 'account_name', 'normal_balance', 'description', 'is_group', 'is_active'])->all() : [];
                Validator::make([...$current, ...$data], $this->accounts->rules($existing))->validate();
                $existing ? $this->accounts->update($existing, $data) : $this->accounts->create($data);
            });
        } catch (ValidationException $exception) {
            return $this->failure($line, $row, collect($exception->errors())->flatten()->values()->all());
        } catch (AccountingException $exception) {
            return $this->failure($line, $row, [$exception->getMessage()]);
        } catch (QueryException $exception) {
            return $this->failure($line, $row, [$this->databaseMessage($exception)]);
        }

        return $this->result($line, $code, $data['account_name'] ?? $existing->account_name ?? '', $existing ? 'update' : 'create', $changes, []);
    }

    /**
     * Resolve the codes and flags of a row into model attributes. Blank cells keep the current value of an
     * existing account; for a new account they default to the parent's type and currency, the base currency
     * and the type's normal balance.
     *
     * @param  array<string, string>  $row
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function attributes(array $row, ?ChartOfAccount $existing, array &$errors): array
    {
        $data = ['account_code' => $row['account_code']];
        $parent = null;

        if (($row['account_name'] ?? '') !== '') {
            $data['account_name'] = $row['account_name'];
        } elseif (! $existing) {
            $errors[] = 'The account name is required.';
        }

        // A blank parent keeps an existing account where it is (a new account becomes top-level).
        if (($row['parent_code'] ?? '') !== '') {
            $parent = ChartOfAccount::query()->where('account_code', $row['parent_code'])->first();
            $parent ? $data['parent_id'] = $parent->id : $errors[] = "Parent account {$row['parent_code']} does not exist in the chart or in the file.";
        }

        $type = null;

        if (($row['account_type'] ?? '') !== '') {
            $type = AccountType::query()->where('code', $row['account_type'])->first()
                ?? AccountType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($row['account_type'])])->first();
            $type ? $data['account_type_id'] = $type->id : $errors[] = "Unknown account type \"{$row['account_type']}\" (use a code or name from Account Types).";
        } elseif (! $existing) {
            $type = $parent ? AccountType::query()->find($parent->account_type_id) : null;
            $type ? $data['account_type_id'] = $type->id : $errors[] = 'The account type is required for a top-level account.';
        }

        if (($row['currency'] ?? '') !== '') {
            $currency = Currency::query()->where('code', strtoupper($row['currency']))->value('id');
            $currency ? $data['currency_id'] = $currency : $errors[] = "Unknown currency \"{$row['currency']}\".";
        } elseif (! $existing) {
            $data['currency_id'] = $parent->currency_id ?? Currency::query()->where('is_base', true)->value('id');
        }

        if (($row['normal_balance'] ?? '') !== '') {
            $balance = mb_strtolower($row['normal_balance']);
            in_array($balance, ['debit', 'credit'], true) ? $data['normal_balance'] = $balance : $errors[] = 'The normal balance must be "debit" or "credit".';
        } elseif (isset($data['account_type_id'])) {
            // The same type keeps the account's side (contra accounts); a new or changed type takes the type's.
            $data['normal_balance'] = $existing && (int) $existing->account_type_id === (int) $data['account_type_id']
                ? $existing->normal_balance
                : $type?->normal_balance;
        }

        foreach (['is_group' => false, 'is_active' => true] as $flag => $default) {
            if (($row[$flag] ?? '') !== '') {
                $value = filter_var($row[$flag], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? match (mb_strtolower($row[$flag])) {
                    'y' => true, 'n' => false, default => null,
                };
                $value === null ? $errors[] = "\"{$row[$flag]}\" is not yes or no ({$this->label($flag)})." : $data[$flag] = $value;
            } elseif (! $existing) {
                $data[$flag] = $default;
            }
        }

        if (($row['control_type'] ?? '') !== '') {
            $data['control_type'] = mb_strtolower($row['control_type']);
        }

        if (array_key_exists('description', $row) && ($row['description'] !== '' || ! $existing)) {
            $data['description'] = $row['description'] === '' ? null : $row['description'];
        }

        return $data;
    }

    private function comparable(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            $value === null => '',
            default => (string) $value,
        };
    }

    private function display(string $field, mixed $value): mixed
    {
        return match ($field) {
            'parent_id' => $value ? ChartOfAccount::query()->whereKey($value)->value('account_code') : null,
            'account_type_id' => $value ? AccountType::query()->whereKey($value)->value('code') : null,
            'currency_id' => $value ? Currency::query()->whereKey($value)->value('code') : null,
            'is_group', 'is_active' => $value === null ? null : ((bool) $value ? 'yes' : 'no'),
            default => $value,
        };
    }

    private function label(string $field): string
    {
        return match ($field) {
            'parent_id' => 'parent',
            'account_type_id' => 'account type',
            'currency_id' => 'currency',
            default => str_replace('_', ' ', $field),
        };
    }

    private function databaseMessage(QueryException $exception): string
    {
        $message = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();

        // Trigger messages (chart guards) are the useful part of the driver error.
        if (preg_match('/(Account[^\n"]*?|Posting[^\n"]*?|Parent[^\n"]*?|A group[^\n"]*?)(?:\s*\(|$|\n|")/i', $message, $match)) {
            return trim($match[1]);
        }

        return 'The database refused this account.';
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $errors
     * @return array{line: int, account_code: string, account_name: string, action: string, changes: array<string, array{0: mixed, 1: mixed}>, errors: list<string>}
     */
    private function failure(int $line, array $row, array $errors): array
    {
        return $this->result($line, $row['account_code'] ?? '', $row['account_name'] ?? '', 'error', [], $errors);
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @param  list<string>  $errors
     * @return array{line: int, account_code: string, account_name: string, action: string, changes: array<string, array{0: mixed, 1: mixed}>, errors: list<string>}
     */
    private function result(int $line, string $code, string $name, string $action, array $changes, array $errors): array
    {
        return ['line' => $line, 'account_code' => $code, 'account_name' => $name, 'action' => $action, 'changes' => $changes, 'errors' => $errors];
    }
}
