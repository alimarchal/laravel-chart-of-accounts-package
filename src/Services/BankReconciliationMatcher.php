<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankReconciliationMatcher
{
    /**
     * @return Collection<int, JournalEntryLine>
     */
    public function candidates(Reconciliation $reconciliation, float|int|string $tolerance = 0.01): Collection
    {
        $bankAccount = $reconciliation->bankAccount()->with('chartOfAccount')->firstOrFail();
        $statementBalance = (float) $reconciliation->statement_balance;
        $bookBalance = (float) $reconciliation->book_balance;
        $targetDifference = abs($statementBalance - $bookBalance);
        $tolerance = max((float) $tolerance, 0.01);

        return JournalEntryLine::query()
            ->with(['journalEntry', 'account'])
            ->where('chart_of_account_id', $bankAccount->chart_of_account_id)
            ->where('reconciliation_status', '!=', 'reconciled')
            ->whereHas('journalEntry', function ($query) use ($reconciliation): void {
                $query->where('status', 'posted')
                    ->whereDate('entry_date', '<=', $reconciliation->statement_date);
            })
            ->when($targetDifference > 0, function ($query) use ($targetDifference, $tolerance): void {
                $query->whereRaw('ABS((debit + credit) - ?) <= ?', [$targetDifference, $tolerance]);
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * @param  array<int, int>  $lineIds
     */
    public function reconcile(Reconciliation $reconciliation, array $lineIds): Reconciliation
    {
        return DB::transaction(function () use ($reconciliation, $lineIds): Reconciliation {
            JournalEntryLine::query()
                ->whereIn('id', $lineIds)
                ->where('reconciliation_status', '!=', 'reconciled')
                ->update([
                    'reconciliation_id' => $reconciliation->id,
                    'reconciliation_status' => 'reconciled',
                    'reconciled_at' => now(),
                    'reconciled_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            $bookBalance = JournalEntryLine::query()
                ->where('reconciliation_id', $reconciliation->id)
                ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
                ->value('balance');

            $reconciliation->forceFill([
                'book_balance' => $bookBalance,
                'status' => round((float) $bookBalance, 2) === round((float) $reconciliation->statement_balance, 2) ? 'completed' : 'draft',
                'completed_at' => round((float) $bookBalance, 2) === round((float) $reconciliation->statement_balance, 2) ? now() : null,
                'completed_by' => round((float) $bookBalance, 2) === round((float) $reconciliation->statement_balance, 2) ? Auth::id() : null,
            ])->save();

            return $reconciliation->refresh()->load('lines');
        });
    }
}
