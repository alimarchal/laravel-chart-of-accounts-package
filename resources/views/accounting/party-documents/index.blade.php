<x-accounting::app-layout title="Invoices & Bills">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoices &amp; Bills</h2>
            <div class="flex flex-wrap gap-2">
                @can('party-documents.create')@foreach (['invoice' => 'Invoice', 'bill' => 'Bill', 'credit_note' => 'Credit note', 'debit_note' => 'Debit note'] as $value => $label)<a href="{{ route('accounting.party-documents.create', ['kind' => $value]) }}" class="inline-flex items-center px-4 py-2 {{ $value === 'invoice' ? 'bg-green-700 text-white hover:bg-green-600' : 'border border-gray-300 hover:bg-gray-50' }} rounded-md font-semibold text-xs uppercase tracking-widest transition">{{ $label }}</a>@endforeach @endcan
                <a href="{{ route('accounting.parties.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    @php($styles = ['draft' => 'bg-amber-100 text-amber-800', 'posted' => 'bg-emerald-100 text-emerald-800', 'void' => 'bg-gray-100 text-gray-600'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <form method="GET" action="{{ route('accounting.party-documents.index') }}" class="flex flex-wrap items-end gap-3">
            <select name="kind" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All kinds</option>@foreach (['invoice' => 'Invoices', 'bill' => 'Bills', 'credit_note' => 'Credit notes', 'debit_note' => 'Debit notes'] as $value => $label)<option value="{{ $value }}" @selected($filters['kind'] === $value)>{{ $label }}</option>@endforeach</select>
            <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">Any status</option>@foreach (['draft', 'posted', 'void'] as $value)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ ucfirst($value) }}</option>@endforeach</select>
            <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Filter</button>
        </form>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Number</th><th class="py-2 px-3 text-left font-medium text-gray-600">Party</th><th class="py-2 px-3 text-left font-medium text-gray-600">Issued</th><th class="py-2 px-3 text-left font-medium text-gray-600">Due</th><th class="py-2 px-3 text-right font-medium text-gray-600">Total</th><th class="py-2 px-3 text-right font-medium text-gray-600">Open</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th></tr></thead>
            <tbody>
            @forelse ($documents as $document)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.party-documents.show', $document['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $document['number'] ?? 'Draft #'.$document['id'] }}</a> <span class="ml-2 text-xs text-gray-500">{{ str_replace('_', ' ', $document['kind']) }}</span></td><td class="py-2 px-3">{{ $document['party'] }}</td><td class="py-2 px-3">{{ $document['issue_date'] }}</td><td class="py-2 px-3">{{ $document['due_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $document['total'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ isset($document['open']) ? number_format((float) $document['open'], 2) : '—' }}</td><td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$document['status']] }}">{{ $document['status'] }}</span></td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No documents yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
