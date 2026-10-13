<x-accounting::app-layout title="Invoices & Bills">
    <x-slot name="header"><x-accounting::page-header title="Invoices & Bills" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.parties.index')"><x-slot name="actions">@can('party-documents.create')@foreach (['invoice' => 'Invoice', 'bill' => 'Bill', 'credit_note' => 'Credit note', 'debit_note' => 'Debit note'] as $value => $label)<a href="{{ route('accounting.party-documents.create', ['kind' => $value]) }}" class="inline-flex items-center px-4 py-2 {{ $value === 'invoice' ? 'bg-green-700 text-white hover:bg-green-600' : 'border border-gray-300 hover:bg-gray-50' }} rounded-md font-semibold text-xs uppercase tracking-widest transition">{{ $label }}</a>@endforeach @endcan</x-slot></x-accounting::page-header></x-slot>
    @php($styles = ['draft' => 'bg-amber-100 text-amber-800', 'posted' => 'bg-emerald-100 text-emerald-800', 'void' => 'bg-gray-100 text-gray-600'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <x-accounting::list-filters :action="route('accounting.party-documents.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'Number, reference or party'],
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'options' => ['invoice' => 'Invoices', 'bill' => 'Bills', 'credit_note' => 'Credit notes', 'debit_note' => 'Debit notes']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'posted' => 'Posted', 'void' => 'Void']],
            ['name' => 'date_from', 'label' => 'Issued from', 'type' => 'date'],
            ['name' => 'date_to', 'label' => 'Issued to', 'type' => 'date'],
            ['name' => 'due_from', 'label' => 'Due from', 'type' => 'date'],
            ['name' => 'due_to', 'label' => 'Due to', 'type' => 'date'],
            ['name' => 'min_total', 'label' => 'Total from', 'type' => 'number'],
            ['name' => 'max_total', 'label' => 'Total up to', 'type' => 'number'],
        ]" />
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Number</th><th class="py-2 px-3 text-left font-medium text-gray-600">Party</th><th class="py-2 px-3 text-left font-medium text-gray-600">Issued</th><th class="py-2 px-3 text-left font-medium text-gray-600">Due</th><th class="py-2 px-3 text-right font-medium text-gray-600">Total</th><th class="py-2 px-3 text-right font-medium text-gray-600">Open</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th></tr></thead>
            <tbody>
            @forelse ($documents as $document)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.party-documents.show', $document['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $document['number'] ?? 'Draft #'.$document['id'] }}</a> <span class="ml-2 text-xs text-gray-500">{{ str_replace('_', ' ', $document['kind']) }}</span></td><td class="py-2 px-3">{{ $document['party'] }}</td><td class="py-2 px-3">{{ $document['issue_date'] }}</td><td class="py-2 px-3">{{ $document['due_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $document['total'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ isset($document['open']) ? number_format((float) $document['open'], 2) : '—' }}</td><td class="py-2 px-3"><x-accounting::status-pill :status="$document['status']" /></td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No documents yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
