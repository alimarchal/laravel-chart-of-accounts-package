<?php

namespace Alimarchal\LaravelChartOfAccounts\Jobs;

use Alimarchal\LaravelChartOfAccounts\Models\WhatsAppMessage;
use Alimarchal\LaravelChartOfAccounts\Services\WhatsAppService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a queued WhatsApp message. A failure that can pass (rate limit, WhatsApp down, a dropped connection) is tried again with a
 * growing pause; one that cannot (a wrong number, the 24-hour window, a template mismatch) stays failed on the record.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(public int $messageId, public int $companyId)
    {
        $this->onQueue((string) config('accounting.whatsapp.queue_name', 'default'));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(WhatsAppService $whatsapp, CurrentCompany $companies): void
    {
        $companies->runAs($this->companyId, function () use ($whatsapp): void {
            $message = WhatsAppMessage::query()->find($this->messageId);

            if ($message === null || $message->status !== 'queued' && $message->status !== 'failed') {
                return;
            }

            $result = $whatsapp->deliver($message);

            if ($result->status === 'failed' && $result->getAttribute('retryable') === true && $this->attempts() < $this->tries) {
                $this->release($this->backoff()[min($this->attempts() - 1, 2)]);
            }
        });
    }
}
