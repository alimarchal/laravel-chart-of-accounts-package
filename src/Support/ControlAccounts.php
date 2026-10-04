<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Support\Facades\Auth;

/**
 * Control accounts summarise a sub-ledger (customers, suppliers, stock, …). Their balance must equal the
 * sub-ledger, so only entries of that module (origin_module) post to them; a manual entry needs the
 * control-accounts.post-manual permission (controller adjustments).
 */
final class ControlAccounts
{
    /**
     * @return array<string, string> type => label
     */
    public static function types(): array
    {
        return (array) config('accounting.control_accounts.types', []);
    }

    public static function label(?string $type): ?string
    {
        return $type === null ? null : (self::types()[$type] ?? ucfirst(str_replace('_', ' ', $type)));
    }

    /**
     * Refuse lines on control accounts the entry's module does not own, unless the current user may post
     * manually to control accounts.
     */
    public static function assertCanPost(JournalEntry $entry): void
    {
        $entry->loadMissing('lines.account');

        $foreign = $entry->lines
            ->map(fn ($line) => $line->account)
            ->filter(fn (?ChartOfAccount $account) => $account?->control_type !== null && $account->control_type !== $entry->origin_module)
            ->unique('id');

        if ($foreign->isEmpty() || Auth::user()?->can('control-accounts.post-manual')) {
            return;
        }

        /** @var ChartOfAccount $account */
        $account = $foreign->first();

        throw new AccountingException(sprintf(
            'Account %s %s is the control account for %s: post through that module, or ask a controller with the "control-accounts.post-manual" permission.',
            $account->account_code,
            $account->account_name,
            mb_strtolower((string) self::label($account->control_type)),
        ));
    }
}
