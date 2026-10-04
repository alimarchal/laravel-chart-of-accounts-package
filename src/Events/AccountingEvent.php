<?php

namespace Alimarchal\LaravelChartOfAccounts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Implemented by every domain event of the package. Listen to this interface to receive all of them:
 *     Event::listen(AccountingEvent::class, MyListener::class);
 */
interface AccountingEvent extends ShouldDispatchAfterCommit
{
    /**
     * Stable event name used in webhooks, e.g. "journal_entry.posted".
     */
    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
