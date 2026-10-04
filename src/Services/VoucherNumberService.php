<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gapless voucher numbers. A number is taken when an entry is posted, inside the posting transaction and
 * under a row lock on the sequence, so numbers follow posting order with no gaps (a rolled-back posting
 * gives its number back) and two concurrent postings never get the same number.
 *
 * Format tokens: {PREFIX}, {FY} (fiscal year: 2026, or 2025-26 when the year does not start in January),
 * {YYYY}, {YY}, {MM}, {SEQ} and {SEQ:n} (zero-padded to n digits).
 */
class VoucherNumberService
{
    /**
     * The voucher type of an entry, falling back to the company's default (JV).
     */
    public function typeFor(JournalEntry $entry): VoucherType
    {
        $query = VoucherType::query()->withoutGlobalScopes()->where('company_id', $entry->company_id);

        if ($entry->voucher_type_id) {
            return (clone $query)->whereKey($entry->voucher_type_id)->first()
                ?? throw new AccountingException('The voucher type of this entry does not belong to its company.');
        }

        // A company created without seed data gets its Journal Voucher type on first posting.
        $default = collect(VoucherType::defaults())->firstWhere('code', VoucherType::DEFAULT_CODE);

        return (clone $query)->where('code', VoucherType::DEFAULT_CODE)->first()
            ?? VoucherType::query()->withoutGlobalScopes()->forceCreate([
                ...$default,
                'company_id' => $entry->company_id,
                'format' => VoucherType::DEFAULT_FORMAT,
                'reset' => VoucherType::RESET_YEARLY,
                'is_active' => true,
                'is_system' => true,
            ]);
    }

    /**
     * Take the next number for a posting entry. Call inside the posting transaction.
     *
     * @return array{voucher_type_id: int, voucher_number: string}
     */
    public function assign(JournalEntry $entry): array
    {
        $type = $this->typeFor($entry);

        if (! $type->is_active) {
            throw new AccountingException("Voucher type {$type->code} is inactive.");
        }

        $date = Carbon::parse($entry->entry_date);
        $scope = $this->scope($type, $date);

        return DB::transaction(function () use ($type, $date, $scope): array {
            DB::table('accounting_voucher_sequences')->insertOrIgnore([
                'company_id' => $type->company_id,
                'voucher_type_id' => $type->id,
                'scope' => $scope,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('accounting_voucher_sequences')
                ->where('voucher_type_id', $type->id)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->next_number;

            DB::table('accounting_voucher_sequences')->where('id', $sequence->id)
                ->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return ['voucher_type_id' => $type->id, 'voucher_number' => $this->format($type, $date, $number)];
        });
    }

    /**
     * The number the next posting of this type on this date would get (nothing is reserved).
     */
    public function preview(VoucherType $type, CarbonInterface|string|null $date = null): string
    {
        $date = Carbon::parse($date ?? now());
        $next = DB::table('accounting_voucher_sequences')
            ->where('voucher_type_id', $type->id)
            ->where('scope', $this->scope($type, $date))
            ->value('next_number');

        return $this->format($type, $date, (int) ($next ?? 1));
    }

    /**
     * The numbering scope a date falls in: its fiscal year, its month, or one series forever.
     */
    public function scope(VoucherType $type, CarbonInterface $date): string
    {
        return match ($type->reset) {
            VoucherType::RESET_MONTHLY => $date->format('Y-m'),
            VoucherType::RESET_NEVER => 'all',
            default => 'FY'.$this->fiscalYearLabel($date, $this->startMonth($type->company_id)),
        };
    }

    public function format(VoucherType $type, CarbonInterface $date, int $number): string
    {
        $fiscalYear = $this->fiscalYearLabel($date, $this->startMonth($type->company_id));

        $formatted = strtr($type->format ?: VoucherType::DEFAULT_FORMAT, [
            '{PREFIX}' => $type->prefix,
            '{FY}' => $fiscalYear,
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
        ]);

        return (string) preg_replace_callback(
            '/\{SEQ(?::(\d{1,2}))?\}/',
            fn (array $match) => str_pad((string) $number, (int) ($match[1] ?? 0), '0', STR_PAD_LEFT),
            $formatted,
        );
    }

    /**
     * "2026" for a calendar fiscal year; "2025-26" for one that starts in another month.
     */
    public function fiscalYearLabel(CarbonInterface $date, int $startMonth): string
    {
        if ($startMonth <= 1) {
            return $date->format('Y');
        }

        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /**
     * Why a format cannot produce unique numbers for its reset rule, or null when it can.
     */
    public static function formatProblem(string $format, string $reset): ?string
    {
        if (! preg_match('/\{SEQ(?::\d{1,2})?\}/', $format)) {
            return 'The format needs a {SEQ} or {SEQ:n} token.';
        }

        if ($reset === VoucherType::RESET_YEARLY && ! str_contains($format, '{FY}')) {
            return 'A series that restarts every fiscal year needs the {FY} token, or numbers would repeat.';
        }

        if ($reset === VoucherType::RESET_MONTHLY && (! str_contains($format, '{MM}') || ! preg_match('/\{(FY|YYYY|YY)\}/', $format))) {
            return 'A series that restarts every month needs {MM} and a year token ({YYYY}, {YY} or {FY}).';
        }

        if (preg_match_all('/\{[A-Z]+(?::\d+)?\}/', $format, $tokens)) {
            $unknown = array_filter(
                array_diff($tokens[0], ['{PREFIX}', '{FY}', '{YYYY}', '{YY}', '{MM}', '{SEQ}']),
                fn (string $token) => ! preg_match('/^\{SEQ:\d{1,2}\}$/', $token),
            );

            if ($unknown !== []) {
                return 'Unknown token(s): '.implode(', ', $unknown).'.';
            }
        }

        return null;
    }

    private function startMonth(int $companyId): int
    {
        return (int) (Company::query()->whereKey($companyId)->value('fiscal_year_start_month') ?: 1);
    }
}
