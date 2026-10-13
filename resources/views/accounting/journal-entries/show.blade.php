@php
    $title = $journalEntry->voucher_number
        ? ($journalEntry->voucherType?->name ?? 'Voucher').' '.$journalEntry->voucher_number
        : ($journalEntry->voucherType?->name ?? 'Journal entry').' (draft #'.$journalEntry->id.')';
    $debit = (float) $journalEntry->lines->sum('debit');
    $credit = (float) $journalEntry->lines->sum('credit');
    $statusTone = ['draft' => 'warn', 'posted' => 'ok', 'void' => 'bad'][$journalEntry->status] ?? '';
    $printIcon = 'M6.72 13.83a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m.38-4.17c-.24.03-.48.06-.72.1m.72-.1H6m11.28 0c.24.03.48.06.72.1m-.72-.1.38 4.17m0 0 .23 2.52a1.13 1.13 0 0 1-1.12 1.23H7.23c-.66 0-1.18-.57-1.12-1.23L6.34 18m11.32 0h1.09A2.25 2.25 0 0 0 21 15.75V9.46c0-1.08-.77-2.02-1.84-2.18a48 48 0 0 0-1.91-.25m-10.5 0V3.38c0-.62.5-1.13 1.13-1.13h8.25c.62 0 1.13.5 1.13 1.13v3.66m-10.5 0a48.5 48.5 0 0 1 10.5 0';
@endphp
<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header :title="$title . ($journalEntry->reference ? ' — '.$journalEntry->reference : '')" :showSearch="false" :showRefresh="false" :showPrint="false" backRoute="accounting.journal-entries.index">
            <x-slot name="actions">
                @if ($journalEntry->status === 'draft')
                    @can('journal-entries.update')
                    <button type="button" class="acc-btn" onclick="var p = document.getElementById('je-edit'); p.toggleAttribute('hidden'); if (!p.hasAttribute('hidden')) p.scrollIntoView({ behavior: 'smooth', block: 'start' })">Edit draft</button>
                    @endcan
                @endif
                @if ($journalEntry->status === 'draft' && $requiresApproval)
                    @if (in_array($journalEntry->approval_status, [null, 'rejected'], true))
                        @can('journal-entries.create')
                        <form method="POST" action="{{ route('accounting.journal-entries.submit', $journalEntry) }}">@csrf<button type="submit" class="acc-btn acc-btn--primary">Submit for approval</button></form>
                        @endcan
                    @endif
                    @if ($journalEntry->approval_status === 'pending')
                        @can('journal-entries.approve')
                        <form method="POST" action="{{ route('accounting.journal-entries.approve', $journalEntry) }}" data-confirm="Approve and post this entry?">@csrf<button type="submit" class="acc-btn acc-btn--success">Approve &amp; post</button></form>
                        <form method="POST" action="{{ route('accounting.journal-entries.reject', $journalEntry) }}" onsubmit="var r = prompt('Reason for rejection'); if (!r) return false; this.reason.value = r;">
                            @csrf
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="acc-btn acc-btn--danger">Reject</button>
                        </form>
                        @endcan
                    @endif
                @endif
                @if ($journalEntry->status === 'draft' && ! $requiresApproval)
                    @can('journal-entries.post')
                    <form method="POST" action="{{ route('accounting.journal-entries.post', $journalEntry) }}" data-confirm="Post this entry? A posted entry can no longer be edited.">@csrf<button type="submit" class="acc-btn acc-btn--success">Post</button></form>
                    @endcan
                @endif
                <x-accounting::more-menu>
                    <a href="{{ route('accounting.journal-entries.print', $journalEntry) }}" target="_blank" >Print voucher</a>
                    <a href="{{ route('accounting.journal-entries.pdf', $journalEntry) }}" target="_blank" >Download PDF</a>
                    @if ($journalEntry->status === 'posted')
                        @can('journal-entries.reverse')
                        <hr>
                        <form method="POST" action="{{ route('accounting.journal-entries.reverse', $journalEntry) }}" data-confirm="Reverse this entry? A new entry with swapped debits and credits is created.">@csrf<button type="submit" class="is-danger">Reverse</button></form>
                        @endcan
                    @endif
                    @if ($journalEntry->status === 'draft')
                        @can('journal-entries.void')
                        <hr>
                        <form method="POST" action="{{ route('accounting.journal-entries.void', $journalEntry) }}" data-confirm="Void this entry?">@csrf<button type="submit" class="is-danger">Void</button></form>
                        @endcan
                    @endif
                </x-accounting::more-menu>
            </x-slot>
        </x-accounting::page-header>
    </x-slot>

    <div class="acc-wrap acc-stack" style="margin-top:1rem">
        <x-accounting::status-message />

        {{-- Summary --}}
        <div class="acc-card">
            <div class="acc-card-b">
                <dl class="je-facts">
                    <div><dt>Status</dt><dd>
                        <span class="acc-badge acc-badge--{{ $statusTone }}">{{ ucfirst($journalEntry->status) }}</span>
                        @if ($journalEntry->approval_status)<span @class(['acc-badge', 'acc-badge--info' => $journalEntry->approval_status === 'pending', 'acc-badge--ok' => $journalEntry->approval_status === 'approved', 'acc-badge--bad' => $journalEntry->approval_status === 'rejected'])>{{ $journalEntry->approval_status === 'pending' ? 'Awaiting approval' : ucfirst($journalEntry->approval_status) }}</span>
                        @elseif ($requiresApproval)<span class="acc-badge acc-badge--info">Approval required</span>@endif
                    </dd></div>
                    <div><dt>Date</dt><dd>{{ $journalEntry->entry_date->format('d M Y') }}</dd></div>
                    <div><dt>Period</dt><dd>@if($journalEntry->accountingPeriod){{ $journalEntry->accountingPeriod->name }}@else<span class="acc-muted">Set when posted</span>@endif</dd></div>
                    <div><dt>Currency</dt><dd>{{ optional($journalEntry->currency)->code }}@if ((float) $journalEntry->fx_rate_to_base !== 1.0) <span class="acc-muted">@ {{ rtrim(rtrim(number_format((float) $journalEntry->fx_rate_to_base, 8, '.', ''), '0'), '.') }}</span>@endif</dd></div>
                    @if (\Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::enabled())
                    <div><dt>Branch</dt><dd>@if($journalEntry->branch)<span class="acc-badge acc-badge--accent">{{ $journalEntry->branch->code }}</span> {{ $journalEntry->branch->name }}@else<span class="acc-muted">Head office / not allocated</span>@endif</dd></div>
                    @endif
                    <div><dt>Reference</dt><dd>{{ $journalEntry->reference ?: '—' }}</dd></div>
                    @if ($journalEntry->source_document_number || $journalEntry->sourceable_type)
                    <div><dt>Source document</dt><dd>
                        @if ($journalEntry->source_document_number){{ \Alimarchal\LaravelChartOfAccounts\Support\SourceDocuments::label($journalEntry->source_document_type) }} <span class="acc-mono" style="font-weight:650">{{ $journalEntry->source_document_number }}</span>@endif
                        @if ($journalEntry->source_document_date)<span class="acc-muted"> dated {{ $journalEntry->source_document_date->format('d M Y') }}</span>@endif
                        @if ($journalEntry->sourceable_type)<div class="acc-muted" style="font-size:.78rem">linked to {{ class_basename($journalEntry->sourceable_type) }} #{{ $journalEntry->sourceable_id }}</div>@endif
                    </dd></div>
                    @endif
                    @if ($journalEntry->description)<div class="acc-span"><dt>Description</dt><dd>{{ $journalEntry->description }}</dd></div>@endif
                    @if ($journalEntry->approval_status === 'rejected' && $journalEntry->rejection_reason)
                    <div class="acc-span"><dt>Rejection reason</dt><dd style="color:var(--acc-bad)">{{ $journalEntry->rejection_reason }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Lines --}}
        <div class="acc-card">
            <div class="acc-card-h"><div><h3>Entry lines</h3></div>
                @if (abs($debit - $credit) < 0.005)<span class="acc-badge acc-badge--ok">Balanced</span>@else<span class="acc-badge acc-badge--bad">Out of balance by {{ number_format(abs($debit - $credit), 2) }}</span>@endif
            </div>
            <div class="acc-table-wrap">
                <table class="acc-table">
                    <thead><tr><th style="width:3rem">#</th><th>Account</th><th>Cost center</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th><th>Note</th></tr></thead>
                    <tbody>
                        @foreach ($journalEntry->lines as $line)
                        <tr>
                            <td class="acc-muted">{{ $line->line_no }}</td>
                            <td>
                                <span class="acc-mono" style="font-weight:700;color:var(--acc-accent)">{{ optional($line->account)->account_code }}</span>
                                <span style="margin-left:.4rem">{{ optional($line->account)->account_name }}</span>
                            </td>
                            <td class="acc-muted">{{ optional($line->costCenter)->name ?: '—' }}</td>
                            <td class="num" style="text-align:right">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                            <td class="num" style="text-align:right">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                            <td class="acc-muted">{{ $line->description }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" style="text-align:right">Totals</td><td class="num" style="text-align:right">{{ number_format($debit, 2) }}</td><td class="num" style="text-align:right">{{ number_format($credit, 2) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($journalEntry->status === 'draft')
        @can('journal-entries.update')
        <div id="je-edit" hidden>
            <div class="acc-card-h" style="border:1px solid var(--acc-border);border-radius:var(--acc-radius);margin-bottom:1rem"><div><h3>Edit draft</h3><p>Change anything, then save: the lines above are replaced.</p></div></div>
            @livewire('accounting.journal-entry-form', ['entry' => $journalEntry])
        </div>
        @endcan
        @endif

        @can('attachments.view')
        <div class="acc-card">
            <div class="acc-card-h">
                <div><h3>Supporting documents ({{ count($attachments ?? []) }})</h3>@if ($journalEntry->status !== 'draft' && count($attachments ?? []))<p>Kept as evidence: posted entries cannot lose attachments.</p>@endif</div>
            </div>
            @forelse ($attachments ?? [] as $attachment)
                <div class="je-attach">
                    <a href="{{ route('accounting.attachments.download', $attachment['id']) }}" style="font-weight:600;color:var(--acc-accent)">{{ $attachment['original_name'] }}</a>
                    <span class="acc-muted" style="font-size:.8rem">{{ number_format($attachment['size'] / 1024, 0) }} KB{{ $attachment['uploaded_by'] ? ' · '.$attachment['uploaded_by'] : '' }}{{ $attachment['description'] ? ' — '.$attachment['description'] : '' }}</span>
                    @if ($journalEntry->status === 'draft')
                        @can('attachments.delete')
                        <form method="POST" action="{{ route('accounting.attachments.destroy', $attachment['id']) }}" style="margin-left:auto" data-confirm="{{ 'Remove '.$attachment['original_name'].'?' }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="acc-btn acc-btn--ghost acc-btn--sm" style="color:var(--acc-bad)">Remove</button>
                        </form>
                        @endcan
                    @endif
                </div>
            @empty
                <div class="acc-empty" style="padding:1.5rem">No documents attached yet.</div>
            @endforelse
            @if ($journalEntry->status !== 'void')
                @can('attachments.create')
                <form method="POST" action="{{ route('accounting.journal-entries.attachments.store', $journalEntry) }}" enctype="multipart/form-data" class="je-attach" style="background:var(--acc-surface-2);flex-wrap:wrap;gap:.75rem">
                    @csrf
                    <div>
                        <input type="file" name="files[]" multiple required style="font-size:.84rem" accept="{{ collect(config('accounting.attachments.mimes', []))->map(fn ($m) => '.'.$m)->implode(',') }}">
                        <div class="acc-hint">Up to {{ round(config('accounting.attachments.max_size_kb', 10240) / 1024) }} MB each.</div>
                        @error('files')<div class="acc-err">{{ $message }}</div>@enderror
                        @error('files.*')<div class="acc-err">{{ $message }}</div>@enderror
                    </div>
                    <input type="text" name="description" placeholder="Description (optional)" class="acc-input" style="width:16rem">
                    <button type="submit" class="acc-btn acc-btn--primary">Attach</button>
                </form>
                @endcan
            @endif
        </div>
        @endcan

        @if (isset($trail) && $trail->isNotEmpty())
        <div class="acc-card">
            <div class="acc-card-h"><div><h3>Audit trail</h3></div></div>
            <ol class="je-trail">
                @foreach ($trail as $step)
                <li><span class="je-dot"></span><div><b>{{ $step['step'] }}</b> <span class="acc-muted">@if ($step['by']) by {{ $step['by'] }} · @endif{{ $step['at']->format('d M Y, H:i') }}</span></div></li>
                @endforeach
            </ol>
        </div>
        @endif
    </div>
    <style>
    .acc-kit .je-facts{display:grid;gap:1rem 1.5rem;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));margin:0}
    .acc-kit .je-facts dt{font-size:.7rem;font-weight:650;text-transform:uppercase;letter-spacing:.05em;color:var(--acc-muted);margin-bottom:.25rem}
    .acc-kit .je-facts dd{margin:0;font-size:.92rem;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
    .acc-kit .je-facts .acc-span{grid-column:1/-1}
    .acc-kit .je-attach{display:flex;align-items:center;gap:.75rem;padding:.7rem 1.1rem;border-top:1px solid var(--acc-border)}
    .acc-kit .je-trail{list-style:none;margin:0;padding:1rem 1.25rem}
    .acc-kit .je-trail li{position:relative;display:flex;gap:.75rem;padding:.35rem 0 .35rem 0;font-size:.86rem}
    .acc-kit .je-dot{flex:none;width:.6rem;height:.6rem;margin-top:.4rem;border-radius:50%;background:var(--acc-accent);box-shadow:0 0 0 3px var(--acc-accent-soft)}
    </style>
</x-accounting::app-layout>
