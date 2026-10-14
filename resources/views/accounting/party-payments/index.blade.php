<x-accounting::app-layout title="Receipts & Payments">
    <x-slot name="header"><x-accounting::page-header title="Receipts & Payments" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.parties.index')"><x-slot name="actions">@can('party-payments.create')<a href="{{ route('accounting.party-payments.create', ['kind' => 'receipt']) }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Receipt</a><a href="{{ route('accounting.party-payments.create', ['kind' => 'payment']) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Payment</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <x-accounting::list-filters :action="route('accounting.party-payments.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'Number, reference or party'],
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'options' => ['receipt' => 'Receipts', 'payment' => 'Payments']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['posted' => 'Posted', 'void' => 'Void']],
            ['name' => 'method', 'label' => 'Method', 'placeholder' => 'cash, bank, cheque…'],
            ['name' => 'date_from', 'label' => 'Paid from', 'type' => 'date'],
            ['name' => 'date_to', 'label' => 'Paid to', 'type' => 'date'],
            ['name' => 'min_amount', 'label' => 'Amount from', 'type' => 'number'],
            ['name' => 'max_amount', 'label' => 'Amount up to', 'type' => 'number'],
        ]" />
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Number</th><th class="py-2 px-3 text-left font-medium text-gray-600">Party</th><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Method</th><th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th><th class="py-2 px-3 text-right font-medium text-gray-600">Not applied</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.party-payments.show', $payment['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $payment['number'] }}</a> <span class="ml-2 text-xs text-gray-500">{{ $payment['kind'] }}</span></td><td class="py-2 px-3">{{ $payment['party'] }}</td><td class="py-2 px-3">{{ $payment['payment_date'] }}</td><td class="py-2 px-3">{{ $payment['method'] ?? '—' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $payment['amount'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $payment['unapplied'] ? number_format((float) $payment['unapplied'], 2) : '—' }}</td><td class="py-2 px-3"><x-accounting::status-pill :status="$payment['status']" /></td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No receipts or payments yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
