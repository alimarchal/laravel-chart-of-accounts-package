<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">@if ($journalEntry->voucher_number){{ $journalEntry->voucherType?->name ?? 'Voucher' }} {{ $journalEntry->voucher_number }}@else{{ $journalEntry->voucherType?->name ?? 'Journal entry' }} (draft #{{ $journalEntry->id }})@endif{{ $journalEntry->reference ? ' — '.$journalEntry->reference : '' }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('accounting.journal-entries.print', $journalEntry) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Print</a>
                <a href="{{ route('accounting.journal-entries.pdf', $journalEntry) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">PDF</a>
                @if ($journalEntry->status === 'draft' && $requiresApproval)
                    @if (in_array($journalEntry->approval_status, [null, 'rejected'], true))
                        @can('journal-entries.create')
                        <form method="POST" action="{{ route('accounting.journal-entries.submit', $journalEntry) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600 transition">Submit for approval</button>
                        </form>
                        @endcan
                    @endif
                    @if ($journalEntry->approval_status === 'pending')
                        @can('journal-entries.approve')
                        <form method="POST" action="{{ route('accounting.journal-entries.approve', $journalEntry) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition" onclick="return confirm('Approve and post this entry?')">Approve &amp; post</button>
                        </form>
                        <form method="POST" action="{{ route('accounting.journal-entries.reject', $journalEntry) }}" class="flex gap-1" onsubmit="var r = prompt('Reason for rejection'); if (!r) return false; this.reason.value = r;">
                            @csrf
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-600 transition">Reject</button>
                        </form>
                        @endcan
                    @endif
                @endif
                @if ($journalEntry->status === 'draft' && ! $requiresApproval)
                    @can('journal-entries.post')
                    <form method="POST" action="{{ route('accounting.journal-entries.post', $journalEntry) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition" onclick="return confirm('Post this entry?')">Post</button>
                    </form>
                    @endcan
                @endif
                @if ($journalEntry->status === 'posted')
                    @can('journal-entries.reverse')
                    <form method="POST" action="{{ route('accounting.journal-entries.reverse', $journalEntry) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-500 transition" onclick="return confirm('Reverse this entry?')">Reverse</button>
                    </form>
                    @endcan
                @endif
                @if ($journalEntry->status === 'draft')
                    @can('journal-entries.void')
                    <form method="POST" action="{{ route('accounting.journal-entries.void', $journalEntry) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition" onclick="return confirm('Void this entry?')">Void</button>
                    </form>
                    @endcan
                @endif
                <a href="{{ route('accounting.journal-entries.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-accounting::status-message class="mb-4 mt-4 shadow-md" />
        @if ($journalEntry->source_document_number || $journalEntry->sourceable_type)
            <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm shadow">
                <span class="font-semibold text-gray-700">Source document</span>
                @if ($journalEntry->source_document_number)
                    <span>{{ \Alimarchal\LaravelChartOfAccounts\Support\SourceDocuments::label($journalEntry->source_document_type) }} <span class="font-mono font-semibold">{{ $journalEntry->source_document_number }}</span></span>
                @endif
                @if ($journalEntry->source_document_date)<span class="text-gray-500">dated {{ $journalEntry->source_document_date->format('Y-m-d') }}</span>@endif
                @if ($journalEntry->sourceable_type)<span class="text-gray-500">linked to {{ class_basename($journalEntry->sourceable_type) }} #{{ $journalEntry->sourceable_id }}</span>@endif
            </div>
        @endif
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div><x-accounting::label value="Date" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$journalEntry->entry_date->format('Y-m-d')" disabled readonly /></div>
                <div><x-accounting::label value="Period" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($journalEntry->accountingPeriod)->name" disabled readonly /></div>
                <div><x-accounting::label value="Currency" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($journalEntry->currency)->code" disabled readonly /></div>
                <div><x-accounting::label value="Status" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100 capitalize" :value="$journalEntry->status" disabled readonly /></div>
                <div><x-accounting::label value="Reference" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$journalEntry->reference" disabled readonly /></div>
                @if ($journalEntry->approval_status || $requiresApproval)
                <div><x-accounting::label value="Approval" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100 capitalize" :value="$journalEntry->approval_status ?? 'required — not submitted'" disabled readonly /></div>
                @endif
                @if ($journalEntry->approval_status === 'rejected')
                <div class="md:col-span-3"><x-accounting::label value="Rejection reason" /><x-accounting::input type="text" class="mt-1 block w-full bg-red-50 text-red-800" :value="$journalEntry->rejection_reason" disabled readonly /></div>
                @endif
                <div class="md:col-span-3"><x-accounting::label value="Description" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$journalEntry->description" disabled readonly /></div>
            </div>
        </div>

        @if ($journalEntry->status === 'draft')
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-4">
            <h3 class="font-semibold text-gray-700 mb-4">Edit Draft</h3>
            @livewire('accounting.journal-entry-form', ['entry' => $journalEntry])
        </div>
        @endif

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="font-semibold text-gray-700 mb-2">Entry Lines</h3>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">#</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Cost Center</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600">Debit</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600">Credit</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($journalEntry->lines as $line)
                    <tr class="border-t border-gray-100">
                        <td class="py-1 px-3">{{ $line->line_no }}</td>
                        <td class="py-1 px-3">{{ optional($line->account)->account_code }} — {{ optional($line->account)->account_name }}</td>
                        <td class="py-1 px-3">{{ optional($line->costCenter)->name }}</td>
                        <td class="py-1 px-3 text-right font-mono">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="py-1 px-3 text-right font-mono">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                        <td class="py-1 px-3 text-gray-500">{{ $line->description }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td colspan="3" class="py-2 px-3 text-right font-semibold">Totals</td>
                        <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($journalEntry->lines->sum('debit'), 2) }}</td>
                        <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($journalEntry->lines->sum('credit'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @can('attachments.view')
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mt-4">
            <div class="flex items-center justify-between border-b p-4">
                <h3 class="font-semibold text-gray-700">Supporting documents ({{ count($attachments ?? []) }})</h3>
                @if ($journalEntry->status !== 'draft' && count($attachments ?? []))<span class="text-xs text-gray-500">Kept as evidence: posted entries cannot lose attachments.</span>@endif
            </div>
            @forelse ($attachments ?? [] as $attachment)
                <div class="flex flex-wrap items-center gap-3 border-b px-4 py-2 text-sm">
                    <a href="{{ route('accounting.attachments.download', $attachment['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $attachment['original_name'] }}</a>
                    <span class="text-gray-500">{{ number_format($attachment['size'] / 1024, 0) }} KB{{ $attachment['uploaded_by'] ? ' · '.$attachment['uploaded_by'] : '' }}{{ $attachment['description'] ? ' — '.$attachment['description'] : '' }}</span>
                    @if ($journalEntry->status === 'draft')
                        @can('attachments.delete')
                        <form method="POST" action="{{ route('accounting.attachments.destroy', $attachment['id']) }}" class="ml-auto" onsubmit="return confirm(@js('Remove '.$attachment['original_name'].'?'))">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-700 hover:underline">Remove</button>
                        </form>
                        @endcan
                    @endif
                </div>
            @empty
                <div class="p-4 text-sm text-gray-500">No documents attached yet.</div>
            @endforelse
            @if ($journalEntry->status !== 'void')
                @can('attachments.create')
                <form method="POST" action="{{ route('accounting.journal-entries.attachments.store', $journalEntry) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 p-4">
                    @csrf
                    <div>
                        <input type="file" name="files[]" multiple required class="text-sm" accept="{{ collect(config('accounting.attachments.mimes', []))->map(fn ($m) => '.'.$m)->implode(',') }}">
                        <div class="text-xs text-gray-500">Up to {{ round(config('accounting.attachments.max_size_kb', 10240) / 1024) }} MB each.</div>
                        @error('files')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                        @error('files.*')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                    </div>
                    <input type="text" name="description" placeholder="Description (optional)" class="rounded-md border-gray-300 text-sm w-64">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600">Attach</button>
                </form>
                @endcan
            @endif
        </div>
        @endcan

        @if (isset($trail) && $trail->isNotEmpty())
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mt-4">
            <h3 class="font-semibold text-gray-700 mb-2">Audit trail</h3>
            <ol class="space-y-1 text-sm">
                @foreach ($trail as $step)
                <li><span class="font-medium">{{ $step['step'] }}</span> <span class="text-gray-500">@if ($step['by']) by {{ $step['by'] }} · @endif{{ $step['at']->format('Y-m-d H:i') }}</span></li>
                @endforeach
            </ol>
        </div>
        @endif
    </div></div>
</x-accounting::app-layout>
