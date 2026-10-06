<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\DocumentSequence;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Gapless numbers for the documents of the receivables / payables sub-ledger: INV-2026-00001, one sequence per
 * company, kind and year. Called inside the posting transaction, so a failed posting gives its number back.
 */
class DocumentNumberService
{
    public const PREFIXES = ['invoice' => 'INV', 'credit_note' => 'CN', 'bill' => 'BILL', 'debit_note' => 'DN', 'receipt' => 'RCT', 'payment' => 'PAY'];

    public function next(string $key, int $year): string
    {
        $number = DB::transaction(function () use ($key, $year): int {
            $sequence = $this->lockedSequence($key, $year);
            $sequence->forceFill(['last_number' => $sequence->last_number + 1])->save();

            return $sequence->last_number;
        });

        return sprintf('%s-%d-%05d', self::PREFIXES[$key] ?? strtoupper($key), $year, $number);
    }

    private function lockedSequence(string $key, int $year): DocumentSequence
    {
        $find = fn () => DocumentSequence::query()->where('company_id', CurrentCompany::currentId())->where('key', $key)->where('year', $year)->lockForUpdate()->first();

        if ($sequence = $find()) {
            return $sequence;
        }

        try {
            DB::transaction(fn () => DocumentSequence::query()->create(['key' => $key, 'year' => $year, 'last_number' => 0]));
        } catch (UniqueConstraintViolationException) {
            // Another request created it first.
        }

        return $find() ?? throw new \RuntimeException('The document sequence could not be created.');
    }
}
