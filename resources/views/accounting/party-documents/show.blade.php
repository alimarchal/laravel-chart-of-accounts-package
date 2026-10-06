<x-accounting::app-layout title="Document">
    <x-slot name="header">
        <x-accounting::page-header :title="ucwords(str_replace('_', ' ', $document['kind'])).' '.($document['number'] ?? '(draft #'.$document['id'].')')" :showSearch="false" backRoute="accounting.party-documents.index" />
    </x-slot>
    @php($m = fn ($v) => number_format((float) $v, 2))
    @php($styles = ['draft' => 'bg-amber-100 text-amber-800', 'posted' => 'bg-emerald-100 text-emerald-800', 'void' => 'bg-gray-100 text-gray-600'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">{{ $document['party'] }} · issued {{ $document['issue_date'] }} · due {{ $document['due_date'] }}@if($document['reference']) · {{ $document['reference'] }}@endif <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$document['status']] }}">{{ $document['status'] }}</span></p>
            <div class="flex flex-wrap items-center gap-2">
                @if ($document['status'] === 'draft')@can('party-documents.update')<a href="{{ route('accounting.party-documents.edit', $document['id']) }}" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Edit</a>@endcan @endif
                @if ($document['status'] === 'draft')@can('party-documents.post')<form method="POST" action="{{ route('accounting.party-documents.post', $document['id']) }}">@csrf<button class="px-3 py-1.5 bg-blue-950 rounded-md text-xs font-semibold text-white uppercase tracking-widest">Post</button></form>@endcan @endif
                @if ($document['status'] === 'draft')@can('party-documents.delete')<form method="POST" action="{{ route('accounting.party-documents.destroy', $document['id']) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')<button class="px-3 py-1.5 text-red-700 text-xs font-semibold uppercase tracking-widest hover:underline">Delete</button></form>@endcan @endif
                @if ($document['status'] === 'posted')@can('party-documents.void')<form method="POST" action="{{ route('accounting.party-documents.void', $document['id']) }}" onsubmit="return confirm('Void this document? Its entry is reversed.')">@csrf<button class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Void</button></form>@endcan @endif
                @if ($document['journal_entry_id'])<a href="{{ route('accounting.journal-entries.show', $document['journal_entry_id']) }}" class="text-xs font-semibold uppercase text-indigo-700 hover:underline">{{ $document['voucher_number'] ?? 'Entry' }}</a>@endif
            </div>
        </div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Account</th><th class="py-2 px-3 text-left font-medium text-gray-600">Description</th><th class="py-2 px-3 text-right font-medium text-gray-600">Qty</th><th class="py-2 px-3 text-right font-medium text-gray-600">Price</th><th class="py-2 px-3 text-left font-medium text-gray-600">Tax</th><th class="py-2 px-3 text-right font-medium text-gray-600">Net</th><th class="py-2 px-3 text-right font-medium text-gray-600">Tax amount</th></tr></thead>
            <tbody>
            @foreach ($document['lines'] as $line)
                <tr class="border-t"><td class="py-2 px-3">{{ $line['account'] }}</td><td class="py-2 px-3">{{ $line['description'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $line['quantity'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($line['unit_price']) }}</td><td class="py-2 px-3">{{ $line['tax_code'] ? $line['tax_code'].' '.(float) $line['tax_rate'].'%' : '—' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($line['net_amount']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($line['tax_amount']) }}</td></tr>
            @endforeach
                <tr class="border-t bg-gray-50 font-medium"><td class="py-2 px-3" colspan="5">Total</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($document['subtotal']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($document['tax_total']) }}</td></tr>
            </tbody>
        </table></div>
        <div class="text-sm tabular-nums">Total {{ $m($document['total']) }}@isset($document['open']) · open {{ $m($document['open']) }}@endisset</div>
        @if (count($allocations))
            <h3 class="text-sm font-medium text-gray-700">Settled with</h3>
            <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm"><tbody>
                @foreach ($allocations as $allocation)
                    <tr class="border-t first:border-t-0"><td class="py-2 px-3">{{ $allocation['source'] }}</td><td class="py-2 px-3">{{ $allocation['document'] }}</td><td class="py-2 px-3">{{ $allocation['allocated_on'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($allocation['amount']) }}</td>
                        <td class="py-2 px-3 text-right">@can('party-payments.create')<form method="POST" action="{{ route('accounting.party-allocations.destroy', $allocation['id']) }}" class="inline">@csrf @method('DELETE')<button class="text-amber-700 hover:underline">Undo</button></form>@endcan</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
        @if (count($openInvoices))@can('party-payments.create')
            <form method="POST" action="{{ route('accounting.party-documents.apply', $document['id']) }}" class="space-y-3">@csrf
                <h3 class="text-sm font-medium text-gray-700">Apply this {{ str_replace('_', ' ', $document['kind']) }} to</h3>
                <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm"><tbody>
                    @foreach ($openInvoices as $i => $open)
                        <tr class="border-t first:border-t-0"><td class="py-2 px-3">{{ $open['number'] }}</td><td class="py-2 px-3">due {{ $open['due_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">open {{ $m($open['open']) }}</td>
                            <td class="py-2 px-3 text-right"><input type="hidden" name="allocations[{{ $i }}][document_id]" value="{{ $open['id'] }}"><input type="number" step="0.01" min="0" name="allocations[{{ $i }}][amount]" placeholder="Amount" class="w-32 border-gray-300 rounded-md text-sm"></td></tr>
                    @endforeach
                </tbody></table></div>
                <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Apply</button>
            </form>
        @endcan @endif
    </div></div>
</x-accounting::app-layout>
