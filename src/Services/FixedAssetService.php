<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AssetDepreciation;
use Alimarchal\LaravelChartOfAccounts\Models\FixedAsset;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The fixed asset register: what the company owns, what it cost, how it loses value and what happened when it left.
 *
 * Depreciation starts in the month the asset enters service (a full month) and is booked month by month, one
 * journal entry per month dated on the month's last day (debit each asset's expense account, credit its accumulated
 * depreciation account). It never takes an asset below its salvage value, and a month is booked once per asset, so
 * running it again does nothing. Disposal removes cost and accumulated depreciation and books the gain or loss.
 */
class FixedAssetService
{
    public const ORIGIN = 'fixed_assets';

    public function __construct(private readonly JournalEntryService $journals) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?FixedAsset $asset = null): array
    {
        $posting = fn (string $type) => CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true)
            ->whereIn('account_type_id', DB::table('accounting_account_types')->where('code', $type)->select('id')));
        $validator = Validator::make($input, [
            'code' => ['required', 'string', 'max:40', CompanyRule::unique('accounting_fixed_assets', 'code')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'acquisition_date' => ['required', 'date'],
            'in_service_date' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'cost' => ['required', 'numeric', 'gt:0', 'max:999999999999999'],
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'lte:cost'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'method' => ['required', Rule::in(array_keys(FixedAsset::METHODS))],
            'declining_rate' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'asset_account_id' => ['required', 'integer', $posting('ASSET')],
            'accumulated_account_id' => ['required', 'integer', $posting('ASSET'), 'different:asset_account_id'],
            'expense_account_id' => ['required', 'integer', $posting('EXPENSE')],
            'offset_account_id' => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
        ]);
        $data = $validator->validate();
        $data['in_service_date'] ??= $data['acquisition_date'];
        $data['salvage_value'] = Money::fromCents(Money::toCents($data['salvage_value'] ?? 0));
        $data['cost'] = Money::fromCents(Money::toCents($data['cost']));

        return $data;
    }

    /**
     * Register an asset. With an offset account (cash, bank or the supplier's account) the purchase is booked too.
     *
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data): FixedAsset {
            $offset = $data['offset_account_id'] ?? null;
            $asset = FixedAsset::query()->create(collect($data)->except('offset_account_id')->all());

            if ($offset) {
                $entry = $this->journals->create([
                    'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                    'origin_module' => self::ORIGIN,
                    'entry_date' => Carbon::parse($asset->acquisition_date)->toDateString(),
                    'reference' => 'ASSET-'.$asset->code,
                    'description' => "Acquisition of {$asset->name}",
                    'lines' => [
                        ['chart_of_account_id' => $asset->asset_account_id, 'debit' => $asset->cost, 'credit' => 0, 'description' => $asset->name],
                        ['chart_of_account_id' => (int) $offset, 'debit' => 0, 'credit' => $asset->cost],
                    ],
                    'auto_post' => true,
                    'system_generated' => true,
                ]);
                $asset->forceFill(['acquisition_entry_id' => $entry->id])->save();
            }

            AccountingAuditLog::record($asset, 'FIXED_ASSET_REGISTERED', null, null, ['code' => $asset->code, 'cost' => $asset->cost, 'acquisition_entry_id' => $asset->acquisition_entry_id]);

            return $asset->refresh();
        });
    }

    /**
     * Descriptive fields may always change; cost, life, method and accounts only before any depreciation is booked.
     *
     * @param  array<string, mixed>  $data  validated
     */
    public function update(FixedAsset $asset, array $data): FixedAsset
    {
        if ($asset->status !== 'active') {
            throw new AccountingException('A disposed asset cannot be changed.');
        }

        $financial = ['acquisition_date', 'in_service_date', 'cost', 'salvage_value', 'useful_life_months', 'method', 'declining_rate', 'asset_account_id', 'accumulated_account_id', 'expense_account_id'];
        $locked = $asset->depreciations()->exists() || $asset->acquisition_entry_id !== null;
        $changes = collect($data)->only(['code', 'name', 'category', 'description', ...($locked ? [] : $financial)])->all();

        if ($locked) {
            foreach ($financial as $field) {
                $new = $data[$field] ?? null;
                $old = $asset->getAttribute($field);
                $same = $old instanceof \DateTimeInterface ? Carbon::parse($old)->toDateString() === Carbon::parse((string) $new)->toDateString() : (is_numeric($old) && is_numeric($new) ? abs((float) $old - (float) $new) < 0.00001 : (string) $old === (string) $new);

                if ($new !== null && ! $same) {
                    throw new AccountingException('Cost, life, method, dates and accounts cannot change once the asset has an acquisition or depreciation entry.');
                }
            }
        }

        $asset->fill($changes)->save();
        AccountingAuditLog::record($asset, 'FIXED_ASSET_UPDATED', null, null, ['code' => $asset->code]);

        return $asset->refresh();
    }

    public function delete(FixedAsset $asset): void
    {
        if ($asset->depreciations()->exists() || $asset->acquisition_entry_id !== null || $asset->status !== 'active') {
            throw new AccountingException('Only an asset without entries can be deleted; dispose of it instead.');
        }

        AccountingAuditLog::record($asset, 'FIXED_ASSET_DELETED', null, null, ['code' => $asset->code]);
        $asset->delete();
    }

    /**
     * Depreciation of one month for an asset, given what has been taken so far (cents).
     */
    public function monthlyAmount(FixedAsset $asset, int $accumulatedCents, int $monthIndex): int
    {
        $depreciable = Money::toCents($asset->cost) - Money::toCents($asset->salvage_value);
        $remaining = max(0, $depreciable - $accumulatedCents);

        if ($remaining === 0 || $monthIndex >= $asset->useful_life_months) {
            return 0;
        }

        if ($asset->method === 'declining_balance') {
            $rate = (float) ($asset->declining_rate ?? (200 / ($asset->useful_life_months / 12)));
            $book = Money::toCents($asset->cost) - $accumulatedCents;
            $amount = (int) round($book * $rate / 100 / 12);

            return $monthIndex === $asset->useful_life_months - 1 ? $remaining : min($remaining, $amount);
        }

        $base = intdiv($depreciable, $asset->useful_life_months);
        $extra = $depreciable - $base * $asset->useful_life_months;

        return min($remaining, $base + ($monthIndex === $asset->useful_life_months - 1 ? $extra : 0));
    }

    /**
     * The months still to book up to a date, with the amount per asset: [month => [asset id => cents]].
     *
     * @param  list<int>|null  $only  limit to these asset ids
     * @return array<string, array<int, int>>
     */
    public function plan(string $upTo, ?array $only = null): array
    {
        $end = Carbon::parse($upTo)->startOfMonth();
        $plan = [];
        $assets = FixedAsset::query()->where('status', 'active')->when($only !== null, fn ($query) => $query->whereIn('id', $only))->orderBy('code')->get();

        foreach ($assets as $asset) {
            $done = AssetDepreciation::query()->where('fixed_asset_id', $asset->id)->pluck('amount', 'period_month')->mapWithKeys(fn ($amount, $month) => [Carbon::parse($month)->format('Y-m') => Money::toCents($amount)]);
            $accumulated = $done->sum();
            $month = Carbon::parse($asset->in_service_date)->startOfMonth();
            $index = 0;

            while ($month->lte($end)) {
                $key = $month->format('Y-m');

                if ($done->has($key)) {
                    $index++;
                    $month->addMonth();

                    continue;
                }

                $amount = $this->monthlyAmount($asset, $accumulated, $index);

                if ($amount > 0) {
                    $plan[$key][$asset->id] = $amount;
                    $accumulated += $amount;
                }

                $index++;
                $month->addMonth();
            }
        }

        ksort($plan);

        return $plan;
    }

    /**
     * What a run up to a date would book, month by month.
     *
     * @return array{up_to: string, months: list<array{month: string, total: string, assets: list<array{asset_id: int, code: string, name: string, amount: string}>}>, total: string}
     */
    public function preview(string $upTo): array
    {
        $plan = $this->plan($upTo);
        $assets = FixedAsset::query()->whereIn('id', collect($plan)->flatMap(fn (array $row) => array_keys($row))->unique()->all())->get()->keyBy('id');
        $months = [];
        $total = 0;

        foreach ($plan as $month => $row) {
            $sum = array_sum($row);
            $total += $sum;
            $months[] = [
                'month' => $month,
                'total' => Money::fromCents($sum),
                'assets' => collect($row)->map(fn (int $cents, int $id): array => ['asset_id' => $id, 'code' => $assets[$id]->code, 'name' => $assets[$id]->name, 'amount' => Money::fromCents($cents)])->values()->all(),
            ];
        }

        return ['up_to' => Carbon::parse($upTo)->endOfMonth()->toDateString(), 'months' => $months, 'total' => Money::fromCents($total)];
    }

    /**
     * Book the depreciation due up to the end of a month: one entry per month, each month all-or-nothing.
     *
     * @param  list<int>|null  $only
     * @return array{months: int, total: string, entries: list<int>}
     */
    public function run(string $upTo, ?array $only = null): array
    {
        $entries = [];
        $total = 0;

        foreach ($this->plan($upTo, $only) as $month => $row) {
            DB::transaction(function () use ($month, $row, &$entries, &$total): void {
                $assets = FixedAsset::query()->whereIn('id', array_keys($row))->get()->keyBy('id');
                $debit = [];
                $credit = [];

                foreach ($row as $id => $cents) {
                    $debit[$assets[$id]->expense_account_id] = ($debit[$assets[$id]->expense_account_id] ?? 0) + $cents;
                    $credit[$assets[$id]->accumulated_account_id] = ($credit[$assets[$id]->accumulated_account_id] ?? 0) + $cents;
                }

                $lines = [];

                foreach ($debit as $account => $cents) {
                    $lines[] = ['chart_of_account_id' => $account, 'debit' => Money::fromCents($cents), 'credit' => 0];
                }

                foreach ($credit as $account => $cents) {
                    $lines[] = ['chart_of_account_id' => $account, 'debit' => 0, 'credit' => Money::fromCents($cents)];
                }

                $date = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
                $entry = $this->journals->create([
                    'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                    'origin_module' => self::ORIGIN,
                    'entry_date' => $date,
                    'reference' => 'DEPR-'.$month,
                    'description' => 'Depreciation for '.Carbon::parse($month.'-01')->format('F Y'),
                    'lines' => $lines,
                    'auto_post' => true,
                    'system_generated' => true,
                ]);

                foreach ($row as $id => $cents) {
                    AssetDepreciation::query()->create(['fixed_asset_id' => $id, 'period_month' => $month.'-01', 'amount' => Money::fromCents($cents), 'journal_entry_id' => $entry->id]);
                    $assets[$id]->forceFill(['accumulated_depreciation' => Money::fromCents(Money::toCents($assets[$id]->accumulated_depreciation) + $cents)])->save();
                }

                AccountingAuditLog::record($entry, 'ASSET_DEPRECIATION_POSTED', null, null, ['month' => $month, 'assets' => count($row), 'total' => Money::fromCents(array_sum($row))]);
                $entries[] = $entry->id;
                $total += array_sum($row);
            });
        }

        return ['months' => count($entries), 'total' => Money::fromCents($total), 'entries' => $entries];
    }

    /**
     * Dispose of an asset (sale, scrapping): depreciation is brought up to the month before the disposal, then
     * cost and accumulated depreciation are removed, proceeds received and the gain or loss booked.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function dispose(FixedAsset $asset, array $input): FixedAsset
    {
        $data = Validator::make($input, [
            'disposal_date' => ['required', 'date', 'after_or_equal:'.Carbon::parse($asset->in_service_date)->toDateString()],
            'proceeds' => ['nullable', 'numeric', 'min:0'],
            'proceeds_account_id' => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
            'gain_loss_account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
            'notes' => ['nullable', 'string', 'max:500'],
        ])->validate();

        if ($asset->status !== 'active') {
            throw new AccountingException('This asset is already disposed of.');
        }

        $proceeds = Money::toCents($data['proceeds'] ?? 0);

        if ($proceeds > 0 && empty($data['proceeds_account_id'])) {
            throw ValidationException::withMessages(['proceeds_account_id' => 'Choose the account that receives the proceeds.']);
        }

        return DB::transaction(function () use ($asset, $data, $proceeds): FixedAsset {
            $date = Carbon::parse($data['disposal_date']);
            $this->run($date->copy()->subMonthNoOverflow()->toDateString(), [$asset->id]);
            $asset->refresh();
            $accumulated = Money::toCents($asset->accumulated_depreciation);
            $cost = Money::toCents($asset->cost);
            $result = $proceeds + $accumulated - $cost; // positive = gain
            $lines = [['chart_of_account_id' => $asset->asset_account_id, 'debit' => 0, 'credit' => Money::fromCents($cost), 'description' => "Disposal of {$asset->name}"]];

            if ($accumulated > 0) {
                $lines[] = ['chart_of_account_id' => $asset->accumulated_account_id, 'debit' => Money::fromCents($accumulated), 'credit' => 0];
            }

            if ($proceeds > 0) {
                $lines[] = ['chart_of_account_id' => (int) $data['proceeds_account_id'], 'debit' => Money::fromCents($proceeds), 'credit' => 0];
            }

            if ($result !== 0) {
                $lines[] = ['chart_of_account_id' => (int) $data['gain_loss_account_id'], 'debit' => $result < 0 ? Money::fromCents(-$result) : 0, 'credit' => $result > 0 ? Money::fromCents($result) : 0, 'description' => $result > 0 ? 'Gain on disposal' : 'Loss on disposal'];
            }

            $entry = $this->journals->create([
                'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                'origin_module' => self::ORIGIN,
                'entry_date' => $date->toDateString(),
                'reference' => 'DISPOSAL-'.$asset->code,
                'description' => $data['notes'] ?? "Disposal of {$asset->name}",
                'lines' => $lines,
                'auto_post' => true,
                'system_generated' => true,
            ]);
            $asset->forceFill([
                'status' => 'disposed', 'disposed_at' => $date->toDateString(), 'disposal_proceeds' => Money::fromCents($proceeds),
                'disposal_gain_loss' => Money::fromCents($result), 'disposal_entry_id' => $entry->id,
            ])->save();
            AccountingAuditLog::record($asset, 'FIXED_ASSET_DISPOSED', null, null, ['disposed_at' => $date->toDateString(), 'proceeds' => Money::fromCents($proceeds), 'gain_loss' => Money::fromCents($result), 'journal_entry_id' => $entry->id]);

            return $asset->refresh();
        });
    }

    /**
     * The register at a date: cost, accumulated depreciation and book value of every asset, with totals.
     *
     * @return array{as_of: string, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function register(?string $asOf = null, ?string $status = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->toDateString();
        $upTo = Carbon::parse($asOf)->startOfMonth()->toDateString();
        $depreciation = AssetDepreciation::query()->whereDate('period_month', '<=', $upTo)->selectRaw('fixed_asset_id, SUM(amount) as total')->groupBy('fixed_asset_id')->pluck('total', 'fixed_asset_id');
        $totals = ['cost' => 0, 'accumulated' => 0, 'book_value' => 0];
        $rows = [];

        foreach (FixedAsset::query()->whereDate('acquisition_date', '<=', $asOf)->when($status, fn ($query) => $query->where('status', $status))->orderBy('code')->get() as $asset) {
            $gone = $asset->disposed_at !== null && Carbon::parse($asset->disposed_at)->toDateString() <= $asOf;
            $cost = Money::toCents($asset->cost);
            $accumulated = $gone ? Money::toCents($asset->accumulated_depreciation) : Money::toCents((string) ($depreciation[$asset->id] ?? 0));
            $book = $gone ? 0 : $cost - $accumulated;

            if (! $gone) {
                $totals['cost'] += $cost;
                $totals['accumulated'] += $accumulated;
                $totals['book_value'] += $book;
            }

            $rows[] = [
                'id' => $asset->id, 'code' => $asset->code, 'name' => $asset->name, 'category' => $asset->category,
                'acquisition_date' => Carbon::parse($asset->acquisition_date)->toDateString(), 'status' => $gone ? 'disposed' : 'active',
                'cost' => Money::fromCents($cost), 'accumulated' => Money::fromCents($accumulated), 'book_value' => Money::fromCents($book),
            ];
        }

        return ['as_of' => $asOf, 'rows' => $rows, 'totals' => array_map(fn (int $cents) => Money::fromCents($cents), $totals)];
    }

    /**
     * The control account of fixed assets against the register: they should agree.
     *
     * @return array{ledger: string, register: string, difference: string}
     */
    public function reconcile(?string $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->toDateString();
        $register = $this->register($asOf);
        $accountIds = FixedAsset::query()->pluck('asset_account_id')->merge(FixedAsset::query()->pluck('accumulated_account_id'))->unique()->all();
        $net = $accountIds === [] ? 0 : DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.company_id', CurrentCompany::currentId())->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf)->whereIn('line.chart_of_account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(line.base_debit), 0) - COALESCE(SUM(line.base_credit), 0) as net')->value('net');
        $ledger = Money::toCents((string) $net);
        $book = Money::toCents($register['totals']['book_value']);

        return ['ledger' => Money::fromCents($ledger), 'register' => Money::fromCents($book), 'difference' => Money::fromCents($ledger - $book)];
    }
}
