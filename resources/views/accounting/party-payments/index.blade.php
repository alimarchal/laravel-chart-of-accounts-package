<x-accounting::app-layout title="Receipts & Payments">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Receipts &amp; Payments</h2>
            <div class="flex gap-2">
                @can('party-payments.create')<a href="{{ route('accounting.party-payments.create', ['kind' => 'receipt']) }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Receipt</a><a href="{{ route('accounting.party-payments.create', ['kind' => 'payment']) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Payment</a>@endcan
                <a href="{{ route('accounting.parties.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Number</th><th class="py-2 px-3 text-left font-medium text-gray-600">Party</th><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Method</th><th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th><th class="py-2 px-3 text-right font-medium text-gray-600">Not applied</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.party-payments.show', $payment['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $payment['number'] }}</a> <span class="ml-2 text-xs text-gray-500">{{ $payment['kind'] }}</span></td><td class="py-2 px-3">{{ $payment['party'] }}</td><td class="py-2 px-3">{{ $payment['payment_date'] }}</td><td class="py-2 px-3">{{ $payment['method'] ?? '—' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $payment['amount'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $payment['unapplied'] ? number_format((float) $payment['unapplied'], 2) : '—' }}</td><td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $payment['status'] === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">{{ $payment['status'] }}</span></td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No receipts or payments yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
