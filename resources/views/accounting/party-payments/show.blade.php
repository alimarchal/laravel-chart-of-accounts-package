<x-accounting::app-layout title="Receipt or Payment">
    <x-slot name="header">
        <x-accounting::page-header :title="ucfirst($payment['kind']).' '.$payment['number']" :showSearch="false" backRoute="accounting.party-payments.index" />
    </x-slot>
    @php($m = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">{{ $payment['party'] }} · {{ $payment['payment_date'] }} · {{ $payment['account'] }}@if($payment['method']) · {{ $payment['method'] }}@endif @if($payment['reference']) · {{ $payment['reference'] }}@endif <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium {{ $payment['status'] === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">{{ $payment['status'] }}</span></p>
            <div class="flex items-center gap-2">
                @if ($payment['status'] === 'posted')@can('party-payments.void')<form method="POST" action="{{ route('accounting.party-payments.void', $payment['id']) }}" onsubmit="return confirm('Void this payment? Its entry is reversed and its allocations released.')">@csrf<button class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Void</button></form>@endcan @endif
                @if ($payment['journal_entry_id'])<a href="{{ route('accounting.journal-entries.show', $payment['journal_entry_id']) }}" class="text-xs font-semibold uppercase text-indigo-700 hover:underline">{{ $payment['voucher_number'] ?? 'Entry' }}</a>@endif
            </div>
        </div>
        <div class="text-sm tabular-nums">Amount {{ $m($payment['amount']) }} · not applied {{ $m($payment['unapplied']) }}</div>
        <h3 class="text-sm font-medium text-gray-700">Settled documents</h3>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm"><tbody>
            @forelse ($payment['allocations'] as $allocation)
                <tr class="border-t first:border-t-0"><td class="py-2 px-3"><a href="{{ route('accounting.party-documents.show', $allocation['document_id']) }}" class="text-indigo-700 hover:underline">{{ $allocation['document'] }}</a></td><td class="py-2 px-3">{{ $allocation['allocated_on'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($allocation['amount']) }}</td><td class="py-2 px-3 text-right">@if($payment['status'] === 'posted')@can('party-payments.create')<form method="POST" action="{{ route('accounting.party-allocations.destroy', $allocation['id']) }}" class="inline">@csrf @method('DELETE')<button class="text-amber-700 hover:underline">Undo</button></form>@endcan @endif</td></tr>
            @empty
                <tr><td class="py-6 px-3 text-center text-gray-500">Not applied to any document yet.</td></tr>
            @endforelse
        </tbody></table></div>
        @if ($payment['status'] === 'posted' && (float) $payment['unapplied'] > 0 && count($openDocuments))@can('party-payments.create')
            <form method="POST" action="{{ route('accounting.party-payments.allocate', $payment['id']) }}" class="space-y-3">@csrf
                <h3 class="text-sm font-medium text-gray-700">Apply the rest to</h3>
                <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm"><tbody>
                    @foreach ($openDocuments as $i => $document)
                        <tr class="border-t first:border-t-0"><td class="py-2 px-3">{{ $document['number'] }}</td><td class="py-2 px-3">due {{ $document['due_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">open {{ $m($document['open']) }}</td><td class="py-2 px-3 text-right"><input type="hidden" name="allocations[{{ $i }}][document_id]" value="{{ $document['id'] }}"><input type="number" step="0.01" min="0" name="allocations[{{ $i }}][amount]" placeholder="Amount" class="w-32 border-gray-300 rounded-md text-sm"></td></tr>
                    @endforeach
                </tbody></table></div>
                <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Apply</button>
            </form>
            <form method="POST" action="{{ route('accounting.party-payments.allocate', $payment['id']) }}">@csrf<input type="hidden" name="auto_allocate" value="1"><button class="text-sm text-indigo-700 hover:underline">Or settle the oldest first</button></form>
        @endcan @endif
    </div></div>
</x-accounting::app-layout>
