<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyAllocation;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyPayment;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Open items, statements of account, ageing and the check of the sub-ledger against the control account.
 *
 * "receivable" is what customers owe us (invoices less credit notes and receipts), "payable" what we owe suppliers
 * (bills less debit notes and payments). Both are positive when the balance is in the normal direction.
 */
class PartyLedgerService
{
    public const BUCKETS = ['not_due', 'days_1_30', 'days_31_60', 'days_61_90', 'over_90'];

    /**
     * Posted invoices / bills with what is still open on them as of a date, and the credits and payments not yet applied.
     *
     * @return array{items: list<array<string, mixed>>, credits: list<array<string, mixed>>, balance: string}
     */
    public function openItems(Party $party, string $side, ?string $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->toDateString();
        $raising = $side === 'receivable' ? 'invoice' : 'bill';
        $credit = $side === 'receivable' ? 'credit_note' : 'debit_note';
        $payment = $side === 'receivable' ? 'receipt' : 'payment';
        $items = [];
        $credits = [];
        $balance = 0;

        $documents = PartyDocument::query()->where('party_id', $party->id)->whereIn('kind', [$raising, $credit])->where('status', 'posted')->whereDate('issue_date', '<=', $asOf)->orderBy('due_date')->orderBy('id')->get();
        $applied = PartyAllocation::query()->where('party_id', $party->id)->whereDate('allocated_on', '<=', $asOf)->get();
        $asDocument = $applied->groupBy('document_id')->map(fn ($rows) => $rows->sum(fn ($row) => Money::toCents((string) $row->amount)));
        $asCredit = $applied->whereNotNull('credit_document_id')->groupBy('credit_document_id')->map(fn ($rows) => $rows->sum(fn ($row) => Money::toCents((string) $row->amount)));

        foreach ($documents as $document) {
            $total = Money::toCents((string) $document->total);

            if ($document->kind === $raising) {
                $open = $total - (int) ($asDocument[$document->id] ?? 0);
                $balance += $open;

                if ($open !== 0) {
                    $items[] = ['id' => $document->id, 'kind' => $document->kind, 'number' => $document->number, 'issue_date' => $document->issue_date->toDateString(), 'due_date' => $document->due_date->toDateString(), 'reference' => $document->reference, 'total' => $document->total, 'open' => Money::fromCents($open), 'days_overdue' => max(0, (int) $document->due_date->diffInDays(Carbon::parse($asOf), false))];
                }
            } else {
                $left = $total - (int) ($asCredit[$document->id] ?? 0);
                $balance -= $left;

                if ($left !== 0) {
                    $credits[] = ['id' => $document->id, 'kind' => $document->kind, 'number' => $document->number, 'date' => $document->issue_date->toDateString(), 'open' => Money::fromCents($left)];
                }
            }
        }

        $payments = PartyPayment::query()->where('party_id', $party->id)->where('kind', $payment)->where('status', 'posted')->whereDate('payment_date', '<=', $asOf)->orderBy('payment_date')->get();
        $byPayment = $applied->whereNotNull('payment_id')->groupBy('payment_id')->map(fn ($rows) => $rows->sum(fn ($row) => Money::toCents((string) $row->amount)));

        foreach ($payments as $row) {
            $left = Money::toCents((string) $row->amount) - (int) ($byPayment[$row->id] ?? 0);
            $balance -= Money::toCents((string) $row->amount);
            // Allocations always sit against documents, so what was applied is already out of the open items.
            $balance += (int) ($byPayment[$row->id] ?? 0);

            if ($left !== 0) {
                $credits[] = ['id' => $row->id, 'kind' => $row->kind, 'number' => $row->number, 'date' => $row->payment_date->toDateString(), 'open' => Money::fromCents($left)];
            }
        }

        return ['items' => $items, 'credits' => $credits, 'balance' => Money::fromCents($balance)];
    }

    /**
     * The party's account between two dates, with a running balance.
     *
     * @return array{opening: string, rows: list<array<string, mixed>>, closing: string}
     */
    public function statement(Party $party, string $side, string $from, string $to): array
    {
        $raising = $side === 'receivable' ? 'invoice' : 'bill';
        $credit = $side === 'receivable' ? 'credit_note' : 'debit_note';
        $payment = $side === 'receivable' ? 'receipt' : 'payment';
        $events = [];

        foreach (PartyDocument::query()->where('party_id', $party->id)->whereIn('kind', [$raising, $credit])->where('status', 'posted')->whereDate('issue_date', '<=', $to)->get() as $document) {
            $events[] = ['date' => $document->issue_date->toDateString(), 'sort' => 0, 'id' => $document->id, 'type' => $document->kind, 'number' => $document->number, 'reference' => $document->reference, 'amount' => Money::toCents((string) $document->total) * ($document->kind === $raising ? 1 : -1)];
        }

        foreach (PartyPayment::query()->where('party_id', $party->id)->where('kind', $payment)->where('status', 'posted')->whereDate('payment_date', '<=', $to)->get() as $row) {
            $events[] = ['date' => $row->payment_date->toDateString(), 'sort' => 1, 'id' => $row->id, 'type' => $row->kind, 'number' => $row->number, 'reference' => $row->reference, 'amount' => -Money::toCents((string) $row->amount)];
        }

        usort($events, fn (array $a, array $b) => [$a['date'], $a['sort'], $a['id']] <=> [$b['date'], $b['sort'], $b['id']]);
        $opening = 0;
        $running = 0;
        $rows = [];

        foreach ($events as $event) {
            $running += $event['amount'];

            if ($event['date'] < $from) {
                $opening = $running;

                continue;
            }

            $rows[] = ['date' => $event['date'], 'type' => $event['type'], 'number' => $event['number'], 'reference' => $event['reference'], 'debit' => $event['amount'] > 0 ? Money::fromCents($event['amount']) : '0.00', 'credit' => $event['amount'] < 0 ? Money::fromCents(-$event['amount']) : '0.00', 'balance' => Money::fromCents($running)];
        }

        return ['opening' => Money::fromCents($opening), 'rows' => $rows, 'closing' => Money::fromCents($running)];
    }

    /**
     * Ageing by party: what is open on invoices (bills) by how long past due, less credits and payments not yet applied.
     *
     * @return array{as_of: string, side: string, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function aging(string $side, ?string $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->toDateString();
        $types = $side === 'receivable' ? ['customer', 'both'] : ['supplier', 'both'];
        $rows = [];
        $totals = array_fill_keys([...self::BUCKETS, 'unapplied', 'total'], 0);

        foreach (Party::query()->whereIn('type', $types)->orderBy('name')->get() as $party) {
            $open = $this->openItems($party, $side, $asOf);
            $row = array_fill_keys([...self::BUCKETS, 'unapplied'], 0);

            foreach ($open['items'] as $item) {
                $days = $item['days_overdue'];
                $bucket = match (true) {
                    $days <= 0 => 'not_due',
                    $days <= 30 => 'days_1_30',
                    $days <= 60 => 'days_31_60',
                    $days <= 90 => 'days_61_90',
                    default => 'over_90',
                };
                $row[$bucket] += Money::toCents($item['open']);
            }

            foreach ($open['credits'] as $credit) {
                $row['unapplied'] -= Money::toCents($credit['open']);
            }

            $row['total'] = array_sum($row);

            if ($row['total'] === 0 && array_sum(array_map('abs', $row)) === 0) {
                continue;
            }

            foreach ($row as $key => $value) {
                $totals[$key] += $value;
            }

            $rows[] = ['party_id' => $party->id, 'code' => $party->code, 'name' => $party->name, 'credit_limit' => $party->credit_limit, ...array_map(fn (int $cents) => Money::fromCents($cents), $row)];
        }

        return ['as_of' => $asOf, 'side' => $side, 'rows' => $rows, 'totals' => array_map(fn (int $cents) => Money::fromCents($cents), $totals)];
    }

    /**
     * The control accounts of the side against the sub-ledger: they should agree to the cent.
     *
     * @return array{ledger: string, subledger: string, difference: string}
     */
    public function reconcile(string $side, ?string $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->toDateString();
        $type = $side === 'receivable' ? 'receivables' : 'payables';
        $accountIds = ChartOfAccount::query()->where('control_type', $type)->pluck('id')->all();
        $extra = Party::query()->whereNotNull($side === 'receivable' ? 'receivable_account_id' : 'payable_account_id')->pluck($side === 'receivable' ? 'receivable_account_id' : 'payable_account_id')->all();
        $accountIds = array_values(array_unique([...$accountIds, ...$extra]));
        $net = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.company_id', CurrentCompany::currentId())->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf)->whereIn('line.chart_of_account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(line.base_debit), 0) - COALESCE(SUM(line.base_credit), 0) as net')->value('net');
        $ledger = Money::toCents((string) $net) * ($side === 'receivable' ? 1 : -1);
        $sub = Money::toCents($this->aging($side, $asOf)['totals']['total']);

        return ['ledger' => Money::fromCents($ledger), 'subledger' => Money::fromCents($sub), 'difference' => Money::fromCents($ledger - $sub)];
    }
}
