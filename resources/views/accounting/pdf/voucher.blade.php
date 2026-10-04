<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }} {{ $subtitle }}</title>@include('accounting::pdf.partials.styles')
<style>
    .grid { margin: 8px 0 12px; }
    .grid td { padding: 3px 6px 3px 0; vertical-align: top; width: 25%; }
    .grid .label { color: #6b7280; font-size: 8px; text-transform: uppercase; }
    .grid .value { font-size: 10px; font-weight: bold; }
    .words { margin: 10px 0; padding: 7px 9px; border: 1px solid #c7d2fe; background: #eef2ff; font-size: 10px; }
    .narration { margin: 6px 0 14px; color: #374151; }
    .signatures { margin-top: 46px; }
    .signatures td { width: 25%; text-align: center; padding: 0 10px; vertical-align: bottom; }
    .signatures .line { border-top: 1px solid #111827; padding-top: 4px; font-size: 8.5px; }
    .signatures .who { font-size: 8px; color: #4b5563; height: 12px; }
    @media print { .screen-only { display: none; } body { margin: 0; } }
    .screen-only { margin: 12px 0; text-align: right; }
    .screen-only button { font-size: 12px; padding: 6px 14px; background: #1e3a8a; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
</style>
</head>
<body @if ($forScreen) style="max-width: 900px; margin: 20px auto; font-size: 11px;" @endif>
    @if ($watermark)<div class="watermark">{{ $watermark }}</div>@endif
    @if ($forScreen)<div class="screen-only"><button type="button" onclick="window.print()">Print</button></div>@endif
    <div class="footer">Printed {{ $printedAt }}{{ $printedBy ? ' by '.$printedBy : '' }} · {{ $company['name'] }}{{ $attachmentsCount ? ' · '.$attachmentsCount.' supporting document(s) on file' : '' }}</div>

    @include('accounting::pdf.partials.letterhead', ['title' => $title, 'subtitle' => $subtitle])

    <table class="grid">
        <tr>
            <td><div class="label">Voucher no.</div><div class="value">{{ $entry->voucher_number ?? 'Not posted' }}</div></td>
            <td><div class="label">Date</div><div class="value">{{ $entry->entry_date->format('d M Y') }}</div></td>
            <td><div class="label">Reference</div><div class="value">{{ $entry->reference ?: '—' }}</div></td>
            <td><div class="label">Currency</div><div class="value">{{ $entry->currency?->code }}{{ (float) $entry->fx_rate_to_base !== 1.0 ? ' @ '.rtrim(rtrim((string) $entry->fx_rate_to_base, '0'), '.') : '' }}</div></td>
        </tr>
        @if ($entry->source_document_number)
        <tr>
            <td colspan="2"><div class="label">Source document</div><div class="value">{{ $documentLabel }} {{ $entry->source_document_number }}</div></td>
            <td colspan="2"><div class="label">Document date</div><div class="value">{{ $entry->source_document_date?->format('d M Y') ?? '—' }}</div></td>
        </tr>
        @endif
    </table>

    @if ($entry->description)<div class="narration"><strong>Narration:</strong> {{ $entry->description }}</div>@endif

    <table class="data">
        <thead><tr><th style="width: 4%">#</th><th style="width: 12%">Account</th><th>Account name / line description</th><th style="width: 12%">Cost center</th><th class="num" style="width: 14%">Debit</th><th class="num" style="width: 14%">Credit</th></tr></thead>
        <tbody>
            @foreach ($entry->lines->sortBy('line_no') as $line)
                <tr>
                    <td>{{ $line->line_no }}</td>
                    <td>{{ $line->account?->account_code }}</td>
                    <td>{{ $line->account?->account_name }}@if ($line->description)<br><span style="color:#6b7280">{{ $line->description }}</span>@endif</td>
                    <td>{{ $line->costCenter?->code }}</td>
                    <td class="num">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '' }}</td>
                    <td class="num">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="4">Total</td><td class="num">{{ number_format((float) $totalDebit, 2) }}</td><td class="num">{{ number_format((float) $totalCredit, 2) }}</td></tr></tfoot>
    </table>

    <div class="words"><strong>Amount in words:</strong> {{ $amountInWords }}</div>

    <table class="signatures">
        <tr>
            <td><div class="who">{{ $entry->creator?->getAttribute('name') }}</div><div class="line">Prepared by</div></td>
            <td><div class="who">{{ $entry->approver?->getAttribute('name') }}</div><div class="line">Approved by</div></td>
            <td><div class="who">{{ $entry->poster?->getAttribute('name') }}</div><div class="line">Posted by</div></td>
            <td><div class="who"></div><div class="line">Received by</div></td>
        </tr>
    </table>
</body></html>
