<?php

namespace Alimarchal\LaravelChartOfAccounts\Listeners;

use Alimarchal\LaravelChartOfAccounts\Events\AccountingEvent;
use Alimarchal\LaravelChartOfAccounts\Jobs\DeliverAccountingWebhook;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fans an accounting event out to config('accounting.webhooks.urls'): the payload is built once,
 * when the event happens, and every endpoint gets its own queued DeliverAccountingWebhook job.
 *
 * Body: {"id": "<event id, same for every endpoint and retry>", "event": "...", "occurred_at": "...", "data": {...}}
 */
class SendAccountingWebhook
{
    public function handle(AccountingEvent $event): void
    {
        $urls = array_values(array_filter((array) config('accounting.webhooks.urls', [])));

        if ($urls === []) {
            return;
        }

        $body = (string) json_encode([
            'id' => (string) Str::uuid(),
            'event' => $event->name(),
            'occurred_at' => now()->toIso8601ZuluString(),
            'data' => $event->payload(),
        ], JSON_UNESCAPED_SLASHES);

        foreach ($urls as $url) {
            try {
                DeliverAccountingWebhook::dispatch((string) $url, $event->name(), (string) Str::uuid(), $body);
            } catch (Throwable $exception) {
                // Only reachable with the "sync" queue driver: a receiver being down must never fail
                // the request that already committed the ledger change. Use a real queue for retries.
                report($exception);
            }
        }
    }
}
