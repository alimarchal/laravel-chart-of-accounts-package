<?php

namespace Alimarchal\LaravelChartOfAccounts\Listeners;

use Alimarchal\LaravelChartOfAccounts\Events\AccountingEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Delivers accounting events to config('accounting.webhooks.urls').
 *
 * Each request carries:
 *   X-Accounting-Event:     e.g. journal_entry.posted
 *   X-Accounting-Delivery:  unique id (use it to de-duplicate retries)
 *   X-Accounting-Signature: t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>" with the shared secret>
 *
 * Queued when the app has a queue; failed deliveries are retried with exponential backoff.
 */
class SendAccountingWebhook implements ShouldQueue
{
    public int $tries;

    public function __construct()
    {
        $this->tries = max(1, (int) config('accounting.webhooks.tries', 5));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900, 3600];
    }

    public function handle(AccountingEvent $event): void
    {
        $urls = (array) config('accounting.webhooks.urls', []);

        if ($urls === []) {
            return;
        }

        $body = (string) json_encode([
            'id' => (string) Str::uuid(),
            'event' => $event->name(),
            'occurred_at' => now()->toISOString(),
            'data' => $event->payload(),
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = (string) time();
        $secret = (string) config('accounting.webhooks.secret', '');
        $delivery = (string) Str::uuid();

        foreach ($urls as $url) {
            Http::timeout((int) config('accounting.webhooks.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Accounting-Event' => $event->name(),
                    'X-Accounting-Delivery' => $delivery,
                    'X-Accounting-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
                ])
                ->withBody($body, 'application/json')
                ->post($url)
                ->throw();
        }
    }
}
