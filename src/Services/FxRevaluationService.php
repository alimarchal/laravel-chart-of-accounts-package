<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\ExchangeRate;
use Alimarchal\LaravelChartOfAccounts\Models\FxRevaluation;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Support\BaseAmounts;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Period-end revaluation of foreign-currency balance sheet accounts.
 *
 * An asset or liability account denominated in a foreign currency holds a foreign balance (what its lines say in
 * that currency) and a carrying value in the base currency (what the ledger has booked for it). Revaluing restates
 * the carrying value to foreign balance × closing rate; the difference is an unrealised exchange gain or loss,
 * booked in one adjusting entry in the base currency against the gain/loss account. Because the carrying value
 * already includes earlier adjustments, running it again at the same rate adjusts nothing. The entry can reverse
 * itself on a later date so the next period starts from the original rates (the usual month-end routine).
 */
class FxRevaluationService
{
    public const ORIGIN = 'fx-revaluation';

    public function __construct(private readonly JournalEntryService $journals) {}

    /**
     * The rate of a currency on a date: the latest rate recorded on or before it, else the currency's own rate.
     */
    public function rateOn(Currency $currency, string $date): string
    {
        $rate = ExchangeRate::query()->where('currency_id', $currency->id)->whereDate('rate_date', '<=', $date)->orderByDesc('rate_date')->value('rate');

        return (string) ($rate ?? $currency->getAttributes()['exchange_rate_to_base'] ?? '1');
    }

    /**
     * What a revaluation at a date would adjust, account by account. $rates overrides the closing rate per currency id.
     *
     * @param  array<int|string, int|float|string>  $rates
     * @return array{as_of_date: string, rows: list<array{chart_of_account_id: int, account_code: string, account_name: string, currency_id: int, currency_code: string, foreign_balance: string, rate: string, carrying_base: string, revalued_base: string, adjustment: string}>, total_gain: string, total_loss: string}
     */
    public function preview(string $asOf, array $rates = []): array
    {
        $asOf = Carbon::parse($asOf)->toDateString();
        $base = Currency::query()->where('is_base', true)->first() ?? throw new AccountingException('There is no base currency yet.');
        $accounts = ChartOfAccount::query()
            ->with(['currency', 'accountType'])
            ->where('is_group', false)->where('currency_id', '<>', $base->id)
            ->whereHas('accountType', fn ($query) => $query->whereIn('code', ['ASSET', 'LIABILITY']))
            ->orderBy('account_code')->get();

        $company = app(CurrentCompany::class)->id();
        $rows = [];
        $gain = 0;
        $loss = 0;

        foreach ($accounts as $account) {
            $foreign = $this->sums($company, $asOf, $account->id, $account->currency_id);

            if ($foreign['foreign'] === 0 && $foreign['carrying'] === 0) {
                continue;
            }

            $rate = (string) ($rates[$account->currency_id] ?? $this->rateOn($account->currency, $asOf));

            if (! is_numeric($rate) || (float) $rate <= 0) {
                throw new AccountingException("The rate for {$account->currency->code} must be greater than zero.");
            }

            $revalued = BaseAmounts::convert($foreign['foreign'], $rate);
            $adjustment = $revalued - $foreign['carrying'];

            if ($adjustment === 0) {
                continue;
            }

            // A positive adjustment raises an asset (or shrinks a liability): a gain; a negative one is a loss.
            $adjustment > 0 ? $gain += $adjustment : $loss += -$adjustment;
            $rows[] = [
                'chart_of_account_id' => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'currency_id' => $account->currency_id,
                'currency_code' => $account->currency->code,
                'foreign_balance' => Money::fromCents($foreign['foreign']),
                'rate' => $rate,
                'carrying_base' => Money::fromCents($foreign['carrying']),
                'revalued_base' => Money::fromCents($revalued),
                'adjustment' => Money::fromCents($adjustment),
            ];
        }

        return ['as_of_date' => $asOf, 'rows' => $rows, 'total_gain' => Money::fromCents($gain), 'total_loss' => Money::fromCents($loss)];
    }

    /**
     * Post the revaluation as of a date.
     *
     * @param  array{as_of_date: string, gain_loss_account_id: int, rates?: array<int|string, int|float|string>, auto_reverse?: bool, reversal_date?: string|null, notes?: string|null}  $data
     */
    public function run(array $data): FxRevaluation
    {
        $asOf = Carbon::parse($data['as_of_date'])->toDateString();
        $account = ChartOfAccount::query()->with('accountType')->find($data['gain_loss_account_id']);

        if ($account === null || $account->is_group || ! $account->is_active || ! in_array($account->accountType->code, ['INCOME', 'EXPENSE'], true)) {
            throw new AccountingException('The gain/loss account must be an active income or expense posting account.');
        }

        $reversalDate = null;

        if (($data['auto_reverse'] ?? false) === true) {
            $reversalDate = Carbon::parse($data['reversal_date'] ?? Carbon::parse($asOf)->addDay())->toDateString();

            if ($reversalDate <= $asOf) {
                throw new AccountingException('The reversal date must be after the revaluation date.');
            }

            $this->assertOpenPeriod($reversalDate, 'reversal');
        }

        $plan = $this->preview($asOf, $data['rates'] ?? []);

        if ($plan['rows'] === []) {
            throw new AccountingException('Nothing to revalue: every foreign-currency balance is already stated at the closing rates.');
        }

        return DB::transaction(function () use ($plan, $account, $asOf, $reversalDate, $data): FxRevaluation {
            $lines = [];
            $net = 0;

            foreach ($plan['rows'] as $row) {
                $cents = Money::toCents($row['adjustment']);
                $net += $cents;
                $lines[] = [
                    'chart_of_account_id' => $row['chart_of_account_id'],
                    $cents > 0 ? 'debit' : 'credit' => Money::fromCents(abs($cents)),
                    'description' => "Revaluation {$row['account_code']} {$row['foreign_balance']} {$row['currency_code']} @ {$row['rate']}",
                ];
            }

            // What the accounts gained is income, what they lost is expense: one line for the net.
            if ($net !== 0) {
                $lines[] = [
                    'chart_of_account_id' => $account->id,
                    $net > 0 ? 'credit' : 'debit' => Money::fromCents(abs($net)),
                    'description' => 'Unrealised exchange '.($net > 0 ? 'gain' : 'loss'),
                ];
            }

            $entry = $this->journals->create([
                'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
                'origin_module' => self::ORIGIN,
                'entry_date' => $asOf,
                'reference' => 'FX-REVAL-'.$asOf,
                'description' => $data['notes'] ?? "Foreign currency revaluation as of {$asOf}",
                'lines' => $lines,
                'auto_post' => true,
                'system_generated' => true,
            ]);

            $revaluation = FxRevaluation::query()->create([
                'as_of_date' => $asOf,
                'gain_loss_account_id' => $account->id,
                'journal_entry_id' => $entry->id,
                'total_gain' => $plan['total_gain'],
                'total_loss' => $plan['total_loss'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($plan['rows'] as $row) {
                $revaluation->lines()->create([
                    'chart_of_account_id' => $row['chart_of_account_id'],
                    'currency_id' => $row['currency_id'],
                    'foreign_balance' => $row['foreign_balance'],
                    'rate' => $row['rate'],
                    'carrying_base' => $row['carrying_base'],
                    'revalued_base' => $row['revalued_base'],
                    'adjustment' => $row['adjustment'],
                ]);
            }

            AccountingAuditLog::record($revaluation, 'FX_REVALUATION_POSTED', null, null, ['as_of_date' => $asOf, 'journal_entry_id' => $entry->id, 'accounts' => count($plan['rows'])]);

            if ($reversalDate !== null) {
                $this->reverse($revaluation, $reversalDate);
            }

            return $revaluation->refresh()->load(['lines.account', 'lines.currency', 'journalEntry', 'reversalEntry']);
        });
    }

    /**
     * Reverse a revaluation's entry on a later date (the carrying values return to what they were).
     */
    public function reverse(FxRevaluation $revaluation, ?string $date = null): FxRevaluation
    {
        if ($revaluation->reversal_entry_id !== null) {
            throw new AccountingException('This revaluation has already been reversed.');
        }

        $date ??= Carbon::parse($revaluation->as_of_date)->addDay()->toDateString();

        if ($date <= $revaluation->as_of_date->toDateString()) {
            throw new AccountingException('The reversal date must be after the revaluation date.');
        }

        $this->assertOpenPeriod($date, 'reversal');
        $reversal = $this->journals->reverse($revaluation->journalEntry, 'Reversal of foreign currency revaluation as of '.$revaluation->as_of_date->toDateString(), $date);
        $revaluation->forceFill(['reversal_entry_id' => $reversal->id, 'reversal_date' => $date])->save();
        AccountingAuditLog::record($revaluation, 'FX_REVALUATION_REVERSED', null, null, ['reversal_entry_id' => $reversal->id, 'reversal_date' => $date]);

        return $revaluation->refresh();
    }

    /**
     * Foreign balance (the account's currency) and carrying value (base currency) in cents, up to a date.
     * Only lines of entries in the account's own currency count towards the foreign balance: the revaluation
     * entries are base-currency adjustments and move the carrying value alone.
     *
     * @return array{foreign: int, carrying: int}
     */
    private function sums(int $company, string $asOf, int $accountId, int $currencyId): array
    {
        $query = fn () => DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.company_id', $company)->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf)
            ->where('line.chart_of_account_id', $accountId);

        $foreign = $query()->where('entry.currency_id', $currencyId)->selectRaw('COALESCE(SUM(line.debit), 0) - COALESCE(SUM(line.credit), 0) as net')->value('net');
        $carrying = $query()->selectRaw('COALESCE(SUM(line.base_debit), 0) - COALESCE(SUM(line.base_credit), 0) as net')->value('net');

        return ['foreign' => Money::toCents((string) $foreign), 'carrying' => Money::toCents((string) $carrying)];
    }

    private function assertOpenPeriod(string $date, string $what): void
    {
        $period = AccountingPeriod::query()->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first();

        if ($period === null || $period->status !== 'open') {
            throw new AccountingException("There is no open accounting period on {$date} for the {$what}: create the next period first or do not auto-reverse.");
        }
    }
}
