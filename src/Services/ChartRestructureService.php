<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Restructuring the chart without rewriting the ledger.
 *
 *  - Renumber: give an account (and, optionally, the sub-accounts sharing its code prefix) a new code. Used
 *    accounts may be renumbered: lines point to the account, not the code. Audited.
 *  - Merge: fold a duplicate account into another. Posted lines are immutable, so the source's balance is moved
 *    with a posted transfer entry (one pair of lines per cost center, through the normal posting rules:
 *    open period, approvals, control accounts); its draft lines, sub-accounts and bank accounts move to the
 *    target; the source is deactivated and remembers where it went. Its history stays in every report.
 */
class ChartRestructureService
{
    /**
     * The codes a renumbering would give: the account, and the sub-accounts whose codes share its prefix.
     *
     * @return array<string, string> old code → new code
     */
    public function renumberPlan(ChartOfAccount $account, string $newCode, bool $withChildren = true): array
    {
        $newCode = trim($newCode);
        $oldCode = $account->account_code;

        if ($newCode === '' || mb_strlen($newCode) > 30) {
            throw new AccountingException('The new code must be 1 to 30 characters.');
        }

        if ($newCode === $oldCode) {
            throw new AccountingException('The new code is the same as the current one.');
        }

        $plan = [$oldCode => $newCode];

        if ($withChildren) {
            // Sub-account codes carry the group's code without its trailing zeros (5100 → 5101, 5110 …). That
            // prefix is replaced and the rest kept: 5100 → 6100 gives 6101, 6110 … The new code must keep the
            // group's shape (length and trailing zeros), or the sub-account codes would change length.
            $oldPrefix = rtrim($oldCode, '0') ?: $oldCode;
            $zeros = strlen($oldCode) - strlen($oldPrefix);
            $children = $this->descendants($account)->filter(fn (ChartOfAccount $child) => str_starts_with($child->account_code, $oldPrefix));

            if ($children->isNotEmpty() && $zeros > 0 && (strlen($newCode) !== strlen($oldCode) || ! str_ends_with($newCode, str_repeat('0', $zeros)))) {
                throw new AccountingException("To move its sub-accounts along, the new code must keep the shape of {$oldCode} (for example "
                    .substr_replace($oldCode, (string) ((((int) $oldPrefix[0]) % 9) + 1), 0, 1).'). Or renumber the account alone.');
            }

            $newPrefix = $zeros > 0 ? substr($newCode, 0, strlen($oldPrefix)) : $newCode;

            foreach ($children as $child) {
                $plan[$child->account_code] = $newPrefix.substr($child->account_code, strlen($oldPrefix));
            }
        }

        foreach ($plan as $from => $to) {
            if (mb_strlen($to) > 30) {
                throw new AccountingException("Renumbering {$from} would give {$to}, longer than 30 characters.");
            }

            $this->assertNotConfigured($from, 'renumbered');
        }

        $taken = ChartOfAccount::query()->whereIn('account_code', array_values($plan))->whereNotIn('account_code', array_keys($plan))->orderBy('account_code')->pluck('account_code');

        if ($taken->isNotEmpty()) {
            throw new AccountingException('These codes are already used: '.$taken->implode(', ').'.');
        }

        return $plan;
    }

    /**
     * @return array<string, string> old code → new code
     */
    public function renumber(ChartOfAccount $account, string $newCode, bool $withChildren = true): array
    {
        return DB::transaction(function () use ($account, $newCode, $withChildren): array {
            $account = ChartOfAccount::query()->lockForUpdate()->findOrFail($account->id);
            $plan = $this->renumberPlan($account, $newCode, $withChildren);
            $accounts = ChartOfAccount::query()->whereIn('account_code', array_keys($plan))->lockForUpdate()->get()->keyBy('account_code');

            // Codes swapped within the plan (5100 → 5200 while 5200 → 5300) would collide on the unique index
            // halfway: park them on temporary codes first.
            if (array_intersect(array_keys($plan), array_values($plan)) !== []) {
                foreach ($accounts as $model) {
                    $model->forceFill(['account_code' => '~'.$model->id])->save();
                }
            }

            foreach ($plan as $from => $to) {
                $accounts[$from]->forceFill(['account_code' => $to])->save();
            }

            AccountingAuditLog::record($account->refresh(), 'ACCOUNT_RENUMBERED', ['codes' => array_keys($plan)], ['codes' => array_values($plan)], ['map' => $plan]);

            return $plan;
        });
    }

    /**
     * What merging $source into $target would do, without doing it.
     *
     * Transfers are the source's balance per cost center and its side (a debit balance lands on the target as a debit).
     *
     * @return array{source: array<string, mixed>, target: array<string, mixed>, transfers: list<array{cost_center: string|null, amount: string, side: string}>, balance: string, draft_lines: int, children: list<string>, bank_accounts: list<string>, problems: list<string>}
     */
    public function mergePlan(ChartOfAccount $source, ChartOfAccount $target): array
    {
        $problems = [];

        if ($source->id === $target->id) {
            $problems[] = 'Choose a different account to merge into.';
        }

        if ((int) $source->account_type_id !== (int) $target->account_type_id || $source->normal_balance !== $target->normal_balance) {
            $problems[] = 'Only accounts of the same type and normal balance can be merged.';
        }

        if ((bool) $source->is_group !== (bool) $target->is_group) {
            $problems[] = 'A group account can only be merged into a group account, and a posting account into a posting account.';
        }

        if ((int) $source->currency_id !== (int) $target->currency_id) {
            $problems[] = 'The accounts have different currencies.';
        }

        $sourceControl = $source->getAttributes()['control_type'] ?? null;
        $targetControl = $target->getAttributes()['control_type'] ?? null;

        if ($sourceControl !== $targetControl) {
            $problems[] = 'Both accounts must control the same sub-ledger (or neither).';
        }

        if (! $target->is_active) {
            $problems[] = "Account {$target->account_code} is inactive.";
        }

        if ($source->is_system) {
            $problems[] = 'System accounts cannot be merged away.';
        }

        if ($this->configuredCode($source->account_code)) {
            $problems[] = "Account {$source->account_code} is referenced by config('accounting.defaults') and cannot be merged away.";
        }

        if ($this->descendants($source)->contains('id', $target->id)) {
            $problems[] = 'An account cannot be merged into one of its own sub-accounts.';
        }

        $pending = JournalEntry::query()->where('status', 'draft')->where('approval_status', 'pending')
            ->whereHas('lines', fn ($lines) => $lines->where('chart_of_account_id', $source->id))->pluck('id');

        if ($pending->isNotEmpty()) {
            $problems[] = 'Entries waiting for approval use this account (#'.$pending->implode(', #').'): approve or reject them first.';
        }

        $balances = $this->balancesByCostCenter($source);
        $transfers = $balances->map(fn (array $balance) => [
            'cost_center' => $balance['cost_center_id'] ? (string) CostCenter::query()->whereKey($balance['cost_center_id'])->value('code') : null,
            'amount' => Money::fromCents(abs($balance['cents'])),
            'side' => $balance['cents'] > 0 ? 'debit' : 'credit',
        ])->values()->all();

        return [
            'source' => $this->present($source),
            'target' => $this->present($target),
            'transfers' => $transfers,
            'balance' => Money::fromCents($balances->sum('cents')),
            'draft_lines' => JournalEntryLine::query()->where('chart_of_account_id', $source->id)
                ->whereHas('journalEntry', fn ($entries) => $entries->where('status', 'draft'))->count(),
            'children' => $source->children()->orderBy('account_code')->pluck('account_code')->all(),
            'bank_accounts' => BankAccount::query()->where('chart_of_account_id', $source->id)->pluck('account_name')->map(fn ($name) => (string) $name)->all(),
            'problems' => $problems,
        ];
    }

    /**
     * Merge $source into $target.
     *
     * @return array{transfer_entry_id: int|null, voucher_number: string|null, moved_draft_lines: int, moved_children: int, moved_bank_accounts: int}
     */
    public function merge(ChartOfAccount $source, ChartOfAccount $target, ?string $date = null, ?string $description = null): array
    {
        return DB::transaction(function () use ($source, $target, $date, $description): array {
            $source = ChartOfAccount::query()->lockForUpdate()->findOrFail($source->id);
            $target = ChartOfAccount::query()->lockForUpdate()->findOrFail($target->id);
            $plan = $this->mergePlan($source, $target);

            if ($plan['problems'] !== []) {
                throw new AccountingException($plan['problems'][0]);
            }

            $entry = $this->postTransfer($source, $target, $date ?? now()->toDateString(), $description);

            // Drafts are still editable: point their lines at the target.
            $draftLines = JournalEntryLine::query()->where('chart_of_account_id', $source->id)
                ->whereHas('journalEntry', fn ($entries) => $entries->where('status', 'draft'))->pluck('id');
            JournalEntryLine::query()->whereKey($draftLines)->get()->each(fn (JournalEntryLine $line) => $line->forceFill(['chart_of_account_id' => $target->id])->save());

            $children = ChartOfAccount::query()->where('parent_id', $source->id)->get();
            $children->each(fn (ChartOfAccount $child) => $child->forceFill(['parent_id' => $target->id])->save());

            $banks = BankAccount::query()->where('chart_of_account_id', $source->id)->get();
            $banks->each(fn (BankAccount $bank) => $bank->forceFill(['chart_of_account_id' => $target->id])->save());

            $metadata = (array) ($source->metadata ?? []);
            $source->forceFill([
                'is_active' => false,
                'metadata' => [...$metadata, 'merged_into' => $target->account_code, 'merged_at' => now()->toISOString()],
            ])->save();

            $result = [
                'transfer_entry_id' => $entry?->id,
                'voucher_number' => $entry?->voucher_number,
                'moved_draft_lines' => $draftLines->count(),
                'moved_children' => $children->count(),
                'moved_bank_accounts' => $banks->count(),
            ];

            AccountingAuditLog::record($source, 'ACCOUNT_MERGED', ['account_code' => $source->account_code, 'is_active' => true], ['merged_into' => $target->account_code, 'is_active' => false], [...$result, 'balance' => $plan['balance']]);

            return $result;
        });
    }

    private function postTransfer(ChartOfAccount $source, ChartOfAccount $target, string $date, ?string $description): ?JournalEntry
    {
        $lines = [];

        foreach ($this->balancesByCostCenter($source) as $balance) {
            $amount = Money::fromCents(abs($balance['cents']));
            $debitBalance = $balance['cents'] > 0;

            $lines[] = ['chart_of_account_id' => $source->id, 'cost_center_id' => $balance['cost_center_id'], 'debit' => $debitBalance ? 0 : $amount, 'credit' => $debitBalance ? $amount : 0, 'description' => "Transfer to {$target->account_code}"];
            $lines[] = ['chart_of_account_id' => $target->id, 'cost_center_id' => $balance['cost_center_id'], 'debit' => $debitBalance ? $amount : 0, 'credit' => $debitBalance ? 0 : $amount, 'description' => "Transfer from {$source->account_code}"];
        }

        if ($lines === []) {
            return null;
        }

        $entry = app(JournalEntryService::class)->create([
            'entry_date' => $date,
            // A sub-ledger's own reclassification: the module that owns both control accounts.
            'origin_module' => $source->getAttributes()['control_type'] ?? null,
            'reference' => "MERGE-{$source->account_code}",
            'description' => $description ?: "Merge of account {$source->account_code} {$source->account_name} into {$target->account_code} {$target->account_name}",
            'lines' => $lines,
        ]);

        return app(PostJournalEntryAction::class)->execute($entry);
    }

    /**
     * The source's posted balance (base currency, debit positive) per cost center, zero balances left out.
     *
     * @return Collection<int, array{cost_center_id: int|null, cents: int}>
     */
    private function balancesByCostCenter(ChartOfAccount $account): Collection
    {
        $rows = JournalEntryLine::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', fn ($entries) => $entries->where('status', 'posted'))
            ->selectRaw('cost_center_id, SUM(COALESCE(base_debit, debit)) AS debits, SUM(COALESCE(base_credit, credit)) AS credits')
            ->groupBy('cost_center_id')
            ->toBase()
            ->get();
        $balances = [];

        foreach ($rows as $row) {
            $cents = Money::toCents((string) $row->debits) - Money::toCents((string) $row->credits);

            if ($cents !== 0) {
                $balances[] = ['cost_center_id' => $row->cost_center_id === null ? null : (int) $row->cost_center_id, 'cents' => $cents];
            }
        }

        return $this->balanceCollection($balances);
    }

    /**
     * @param  list<array{cost_center_id: int|null, cents: int}>  $balances
     * @return Collection<int, array{cost_center_id: int|null, cents: int}>
     */
    private function balanceCollection(array $balances): Collection
    {
        return new Collection($balances);
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    private function descendants(ChartOfAccount $account): Collection
    {
        $all = ChartOfAccount::query()->get(['id', 'parent_id', 'account_code'])->groupBy('parent_id');
        $found = collect();
        $queue = [$account->id];

        while ($queue !== []) {
            foreach ($all->get(array_shift($queue), collect()) as $child) {
                $found->push($child);
                $queue[] = $child->id;
            }
        }

        return $found;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ChartOfAccount $account): array
    {
        return [
            'id' => $account->id,
            'account_code' => $account->account_code,
            'account_name' => $account->account_name,
            'is_group' => (bool) $account->is_group,
            'is_active' => (bool) $account->is_active,
        ];
    }

    private function configuredCode(string $code): bool
    {
        return in_array($code, array_filter((array) config('accounting.defaults', []), fn ($value, $key) => str_ends_with((string) $key, '_account_code'), ARRAY_FILTER_USE_BOTH), true);
    }

    private function assertNotConfigured(string $code, string $what): void
    {
        if ($this->configuredCode($code)) {
            throw new AccountingException("Account {$code} is referenced by config('accounting.defaults'): change the configured code first, then it can be {$what}.");
        }
    }
}
