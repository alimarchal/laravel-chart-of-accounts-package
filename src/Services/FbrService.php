<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Contracts\FbrGateway;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\FbrSubmission;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocumentLine;
use Alimarchal\LaravelChartOfAccounts\Support\Fbr\FakeFbrGateway;
use Alimarchal\LaravelChartOfAccounts\Support\Fbr\HttpFbrGateway;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Sales tax invoicing with FBR: posted sales invoices and credit notes are turned into the digital-invoice payload,
 * sent through a gateway, and the invoice number FBR returns is kept against the document.
 *
 * The payload follows the field names of FBR's digital invoicing format (seller and buyer NTN, province and address,
 * one item per line with HS code, unit of measure, rate and tax), taking what the books do not hold (HS code, unit of
 * measure, sale type, seller identity) from accounting.fbr. An invoice is sent once: an accepted one is never sent
 * again, a failed one can be retried, and every attempt keeps what was sent and received. The built-in "fake" mode
 * accepts locally; "live" posts to the URL you configure, or bind your own FbrGateway.
 */
class FbrService
{
    public static function gatewayFor(string $mode): FbrGateway
    {
        return $mode === 'live' ? new HttpFbrGateway : new FakeFbrGateway;
    }

    public function enabled(): bool
    {
        return (bool) config('accounting.fbr.enabled', false) && app(FeatureManager::class)->enabled('fbr');
    }

    /**
     * Why a document cannot be sent, or null when it can.
     */
    public function blocker(PartyDocument $document): ?string
    {
        $party = Party::query()->find($document->party_id);

        return match (true) {
            ! in_array($document->kind, ['invoice', 'credit_note'], true) => 'Only sales invoices and credit notes go to FBR.',
            $document->status !== 'posted' => 'Post the document first.',
            $party === null => 'The customer no longer exists.',
            default => null,
        };
    }

    /**
     * The payload for a document.
     *
     * @return array<string, mixed>
     */
    public function payload(PartyDocument $document): array
    {
        $party = Party::query()->findOrFail($document->party_id);
        $seller = (array) config('accounting.fbr.seller', []);
        $defaults = (array) config('accounting.fbr.defaults', []);
        $credit = $document->kind === 'credit_note';
        $original = $credit && $document->reference ? PartyDocument::query()->where('number', $document->reference)->first() : null;
        $fbrOfOriginal = $original ? FbrSubmission::query()->where('party_document_id', $original->id)->where('status', 'accepted')->value('fbr_invoice_number') : null;
        $items = PartyDocumentLine::query()->where('document_id', $document->id)->orderBy('line_no')->get()->map(function (PartyDocumentLine $line) use ($defaults): array {
            $net = (float) $line->net_amount;
            $tax = (float) $line->tax_amount;

            return [
                'hsCode' => (string) ($defaults['hs_code'] ?? ''),
                'productDescription' => (string) ($line->description ?? ''),
                'rate' => rtrim(rtrim(number_format((float) ($line->tax_rate ?? 0), 2, '.', ''), '0'), '.').'%',
                'uoM' => (string) ($defaults['uom'] ?? ''),
                'quantity' => (float) $line->quantity,
                'totalValues' => round($net + $tax, 2),
                'valueSalesExcludingST' => round($net, 2),
                'fixedNotifiedValueOrRetailPrice' => 0,
                'salesTaxApplicable' => round($tax, 2),
                'salesTaxWithheldAtSource' => 0,
                'extraTax' => '',
                'furtherTax' => 0,
                'sroScheduleNo' => '',
                'fedPayable' => 0,
                'discount' => 0,
                'saleType' => (string) ($defaults['sale_type'] ?? ''),
                'sroItemSerialNo' => '',
            ];
        })->values()->all();

        return [
            'invoiceType' => $credit ? 'Debit Note' : 'Sale Invoice',
            'invoiceDate' => Carbon::parse($document->issue_date)->toDateString(),
            'sellerNTNCNIC' => (string) ($seller['ntn'] ?? ''),
            'sellerBusinessName' => (string) ($seller['business_name'] ?? ''),
            'sellerProvince' => (string) ($seller['province'] ?? ''),
            'sellerAddress' => (string) ($seller['address'] ?? ''),
            'buyerNTNCNIC' => (string) ($party->tax_number ?? ''),
            'buyerBusinessName' => $party->name,
            'buyerProvince' => (string) ($defaults['buyer_province'] ?? ''),
            'buyerAddress' => (string) ($party->address ?? ''),
            'buyerRegistrationType' => $party->tax_number ? 'Registered' : 'Unregistered',
            'invoiceRefNo' => $credit ? (string) ($fbrOfOriginal ?? '') : '',
            'scenarioId' => (string) ($defaults['scenario_id'] ?? ''),
            'items' => $items,
            'internalNumber' => $document->number,
        ];
    }

    /**
     * Send a document (or retry a failed one) and keep the outcome.
     *
     * @throws AccountingException when the document cannot be sent or has already been accepted
     */
    public function submit(PartyDocument $document): FbrSubmission
    {
        if (! $this->enabled()) {
            throw new AccountingException('FBR integration is switched off (the fbr feature, or accounting.fbr.enabled).');
        }

        if ($reason = $this->blocker($document)) {
            throw new AccountingException($reason);
        }

        $mode = (string) config('accounting.fbr.mode', 'fake');
        $gateway = app()->bound(FbrGateway::class) ? app(FbrGateway::class) : self::gatewayFor($mode);

        return DB::transaction(function () use ($document, $mode, $gateway): FbrSubmission {
            $submission = FbrSubmission::query()->where('party_document_id', $document->id)->lockForUpdate()->first();

            if ($submission?->status === 'accepted') {
                throw new AccountingException("{$document->number} was already accepted by FBR as {$submission->fbr_invoice_number}.");
            }

            $payload = $this->payload($document);
            $submission ??= new FbrSubmission(['party_document_id' => $document->id]);
            $result = $gateway->submit($payload);
            $submission->forceFill([
                'status' => $result['accepted'] ? 'accepted' : 'failed',
                'mode' => $mode,
                'attempts' => ((int) $submission->attempts) + 1,
                'fbr_invoice_number' => $result['invoice_number'],
                'request' => json_encode($payload, JSON_THROW_ON_ERROR),
                'response' => $result['response'],
                'error' => $result['error'],
                'submitted_at' => now(),
                'created_by' => $submission->created_by ?? Auth::id(),
            ])->save();
            AccountingAuditLog::record($submission, $result['accepted'] ? 'FBR_INVOICE_ACCEPTED' : 'FBR_INVOICE_FAILED', null, null, ['document' => $document->number, 'fbr_invoice_number' => $result['invoice_number'], 'error' => $result['error']]);

            return $submission->refresh();
        });
    }

    /**
     * Sales documents and what happened to them, newest first: each with its FBR status (none = not sent yet).
     *
     * @return list<array<string, mixed>>
     */
    public function overview(?string $status = null): array
    {
        $documents = PartyDocument::query()->whereIn('kind', ['invoice', 'credit_note'])->where('status', 'posted')->orderByDesc('issue_date')->orderByDesc('id')->limit(300)->get();
        $submissions = FbrSubmission::query()->whereIn('party_document_id', $documents->pluck('id'))->get()->keyBy('party_document_id');
        $parties = Party::query()->whereIn('id', $documents->pluck('party_id'))->pluck('name', 'id');

        $rows = $documents->map(function (PartyDocument $document) use ($submissions, $parties): array {
            $submission = $submissions->get($document->id);

            return [
                'document_id' => $document->id, 'number' => $document->number, 'kind' => $document->kind, 'issue_date' => Carbon::parse($document->issue_date)->toDateString(), 'customer' => $parties[$document->party_id] ?? '',
                'total' => $document->total, 'status' => $submission !== null ? $submission->status : 'none', 'fbr_invoice_number' => $submission?->fbr_invoice_number, 'error' => $submission?->error, 'attempts' => $submission !== null ? (int) $submission->attempts : 0,
                'submitted_at' => $submission?->submitted_at?->toISOString(), 'mode' => $submission?->mode,
            ];
        })->values()->all();

        return $status ? array_values(array_filter($rows, fn (array $row): bool => $row['status'] === $status)) : $rows;
    }
}
