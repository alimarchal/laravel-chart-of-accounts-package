<x-accounting::app-layout title="Customer or Supplier">
    <x-slot name="header">
        <x-accounting::page-header :title="$party['code'].' · '.$party['name']" :showSearch="false" backRoute="accounting.parties.index" />
    </x-slot>
    @php($m = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        @if ($party['credit_limit'] !== null && $side === 'receivable' && (float) $openItems['balance'] > (float) $party['credit_limit'])<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">Over the credit limit: the balance {{ $m($openItems['balance']) }} is above the limit of {{ $m($party['credit_limit']) }}.</div>@endif
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">{{ $party['type'] }}@if($party['tax_number']) · tax no. {{ $party['tax_number'] }}@endif · terms {{ $party['payment_terms_days'] }} days @if($party['credit_limit']) · credit limit {{ $m($party['credit_limit']) }}@endif @unless($party['is_active']) · inactive @endunless</p>
            <div class="flex flex-wrap gap-2">
                @if ($party['type'] === 'both')<a href="{{ route('accounting.parties.show', ['party' => $party['id'], 'side' => $side === 'receivable' ? 'payable' : 'receivable']) }}" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Show {{ $side === 'receivable' ? 'payables' : 'receivables' }}</a>@endif
                @can('party-documents.create')<a href="{{ route('accounting.party-documents.create', ['kind' => $side === 'receivable' ? 'invoice' : 'bill']) }}" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">{{ $side === 'receivable' ? 'New invoice' : 'New bill' }}</a>@endcan
                @can('party-payments.create')<a href="{{ route('accounting.party-payments.create', ['kind' => $side === 'receivable' ? 'receipt' : 'payment', 'party_id' => $party['id']]) }}" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">{{ $side === 'receivable' ? 'Receive payment' : 'Pay supplier' }}</a>@endcan
                @can('parties.update')<a href="{{ route('accounting.parties.edit', $party['id']) }}" class="px-3 py-1.5 text-indigo-700 text-xs font-semibold uppercase tracking-widest hover:underline">Edit</a>@endcan
            </div>
        </div>
        <div class="bg-white shadow rounded-lg p-3"><div class="text-xs uppercase text-gray-500">{{ $side === 'receivable' ? 'They owe you' : 'You owe them' }}</div><div class="mt-1 text-2xl tabular-nums">{{ $m($openItems['balance']) }}</div></div>
        <h3 class="text-sm font-medium text-gray-700">Open items</h3>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Document</th><th class="py-2 px-3 text-left font-medium text-gray-600">Issued</th><th class="py-2 px-3 text-left font-medium text-gray-600">Due</th><th class="py-2 px-3 text-right font-medium text-gray-600">Total</th><th class="py-2 px-3 text-right font-medium text-gray-600">Open</th><th class="py-2 px-3 text-right font-medium text-gray-600">Days overdue</th></tr></thead>
            <tbody>
            @forelse ($openItems['items'] as $item)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.party-documents.show', $item['id']) }}" class="text-indigo-700 hover:underline">{{ $item['number'] }}</a> <span class="text-xs text-gray-500">{{ $item['reference'] }}</span></td><td class="py-2 px-3">{{ $item['issue_date'] }}</td><td class="py-2 px-3">{{ $item['due_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($item['total']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($item['open']) }}</td><td class="py-2 px-3 text-right tabular-nums {{ $item['days_overdue'] > 0 ? 'text-red-700' : '' }}">{{ $item['days_overdue'] ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-500">Nothing open.</td></tr>
            @endforelse
            @foreach ($openItems['credits'] as $credit)
                <tr class="border-t text-gray-500"><td class="py-2 px-3" colspan="4">{{ $credit['number'] }} ({{ str_replace('_', ' ', $credit['kind']) }}, not yet applied)</td><td class="py-2 px-3 text-right tabular-nums">-{{ $m($credit['open']) }}</td><td></td></tr>
            @endforeach
            </tbody>
        </table></div>
        <h3 class="text-sm font-medium text-gray-700">Statement</h3>
        <form method="GET" action="{{ route('accounting.parties.show', $party['id']) }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="side" value="{{ $side }}">
            <div><x-accounting::label for="date_from" value="From" /><x-accounting::input id="date_from" name="date_from" type="date" class="mt-1 block" :value="$range['date_from']" /></div>
            <div><x-accounting::label for="date_to" value="To" /><x-accounting::input id="date_to" name="date_to" type="date" class="mt-1 block" :value="$range['date_to']" /></div>
            <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Apply</button>
        </form>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Document</th><th class="py-2 px-3 text-right font-medium text-gray-600">Debit</th><th class="py-2 px-3 text-right font-medium text-gray-600">Credit</th><th class="py-2 px-3 text-right font-medium text-gray-600">Balance</th></tr></thead>
            <tbody>
                <tr class="border-t bg-gray-50"><td class="py-2 px-3" colspan="4">Opening balance</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($statement['opening']) }}</td></tr>
                @foreach ($statement['rows'] as $row)
                    <tr class="border-t"><td class="py-2 px-3">{{ $row['date'] }}</td><td class="py-2 px-3">{{ $row['number'] }} <span class="text-xs text-gray-500">{{ str_replace('_', ' ', $row['type']) }}</span></td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $row['debit'] ? $m($row['debit']) : '' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $row['credit'] ? $m($row['credit']) : '' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($row['balance']) }}</td></tr>
                @endforeach
                <tr class="border-t bg-gray-50 font-medium"><td class="py-2 px-3" colspan="4">Closing balance</td><td class="py-2 px-3 text-right tabular-nums">{{ $m($statement['closing']) }}</td></tr>
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
