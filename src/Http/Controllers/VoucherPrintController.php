<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\PdfRenderer;
use Alimarchal\LaravelChartOfAccounts\Support\AmountInWords;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\SourceDocuments;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Printed voucher of a journal entry: an HTML page for the browser's print dialog, and a PDF.
 */
class VoucherPrintController extends Controller
{
    public function show(JournalEntry $journalEntry, PdfRenderer $renderer): View
    {
        return view('accounting::pdf.voucher', [...$this->data($journalEntry, $renderer), 'forScreen' => true]);
    }

    public function pdf(JournalEntry $journalEntry, PdfRenderer $renderer): Response
    {
        abort_unless($renderer->available(), 501, 'PDF vouchers need dompdf: composer require dompdf/dompdf. Use the print view meanwhile.');

        $name = ($journalEntry->voucher_number ?? 'draft-'.$journalEntry->id).'.pdf';

        return response($renderer->render('accounting::pdf.voucher', [...$this->data($journalEntry, $renderer), 'forScreen' => false]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(JournalEntry $entry, PdfRenderer $renderer): array
    {
        $entry->load(['lines.account', 'lines.costCenter', 'currency', 'voucherType', 'creator', 'poster', 'approver']);
        $debit = $entry->lines->sum(fn ($line) => Money::toCents((string) $line->getRawOriginal('debit')));
        $credit = $entry->lines->sum(fn ($line) => Money::toCents((string) $line->getRawOriginal('credit')));

        return [
            'entry' => $entry,
            'company' => $renderer->company(),
            'title' => $entry->voucherType?->name ?? 'Journal Voucher',  // @phpstan-ignore nullsafe.neverNull (voucher_type_id is nullable on older rows)
            'subtitle' => $entry->voucher_number ?? 'Draft #'.$entry->id,
            'documentLabel' => SourceDocuments::label($entry->source_document_type),
            'totalDebit' => Money::fromCents($debit),
            'totalCredit' => Money::fromCents($credit),
            'amountInWords' => AmountInWords::convert(Money::fromCents($debit), $entry->currency?->code),  // @phpstan-ignore nullsafe.neverNull
            'attachmentsCount' => $entry->attachments()->count(),
            'watermark' => match ($entry->status) {
                'draft' => 'DRAFT',
                'void' => 'VOID',
                default => $entry->reversed_by_entry_id ? 'REVERSED' : null,
            },
            'printedAt' => now()->format('d M Y H:i'),
            'printedBy' => auth()->user()?->getAttribute('name'),
        ];
    }
}
