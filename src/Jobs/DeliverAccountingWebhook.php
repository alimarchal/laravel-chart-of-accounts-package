<?php

namespace Alimarchal\LaravelChartOfAccounts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * One webhook delivery to one endpoint. Each endpoint gets its own job, so a failing receiver is
 * retried on its own and never causes duplicates at the others.
 *
 * Headers:
 *   X-Accounting-Event:     e.g. journal_entry.posted
 *   X-Accounting-Delivery:  stays the same across retries of this delivery (de-duplicate on it)
 *   X-Accounting-Signature: t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>" with the shared secret>
 *
 * The body is fixed when the event happens; only the signature timestamp is fresh on each attempt.
 */
class DeliverAccountingWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly string $url,
        public readonly string $event,
        public readonly string $deliveryId,
        public readonly string $body,
    ) {
        $this->tries = max(1, (int) config('accounting.webhooks.tries', 5));
        $this->timeout = max(1, (int) config('accounting.webhooks.timeout', 10)) + 5;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $timestamp = (string) now()->getTimestamp();
        $secret = (string) config('accounting.webhooks.secret', '');

        Http::timeout(max(1, (int) config('accounting.webhooks.timeout', 10)))
            ->withHeaders([
                'X-Accounting-Event' => $this->event,
                'X-Accounting-Delivery' => $this->deliveryId,
                'X-Accounting-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$this->body, $secret),
            ])
            ->withBody($this->body, 'application/json')
            ->post($this->url)
            ->throw();
    }
}
