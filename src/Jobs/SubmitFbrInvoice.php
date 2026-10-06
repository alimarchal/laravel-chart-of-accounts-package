<?php

namespace Alimarchal\LaravelChartOfAccounts\Jobs;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Services\FbrService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends a posted sales invoice to FBR in the background (accounting.fbr.auto_submit). A refusal by FBR is recorded on
 * the submission and is not retried by the queue: fix the invoice and retry it from the FBR screen.
 */
class SubmitFbrInvoice implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $documentId, public readonly int $companyId) {}

    public function handle(FbrService $fbr): void
    {
        app(CurrentCompany::class)->runAs($this->companyId, function () use ($fbr): void {
            $document = PartyDocument::query()->find($this->documentId);

            if ($document === null) {
                return;
            }

            try {
                $fbr->submit($document);
            } catch (AccountingException) {
                // Not eligible (switched off, not a sales document, already accepted): nothing to send.
            }
        });
    }
}
