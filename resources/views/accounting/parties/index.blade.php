<x-accounting::app-layout title="Customers & Suppliers">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customers &amp; Suppliers</h2>
            <div class="flex flex-wrap gap-2">
                @can('parties.create')<a href="{{ route('accounting.parties.create') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">New customer or supplier</a>@endcan
                <a href="{{ route('accounting.party-documents.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Invoices &amp; bills</a>
                <a href="{{ route('accounting.party-payments.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Receipts &amp; payments</a>
                <a href="{{ route('accounting.receivables.aging', ['side' => 'receivable']) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Ageing</a>
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <form method="GET" action="{{ route('accounting.parties.index') }}" class="flex flex-wrap items-end gap-3">
            <input name="search" value="{{ $filters['search'] }}" placeholder="Search by name or code" class="border-gray-300 rounded-md shadow-sm text-sm w-64">
            <select name="type" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All</option>@foreach (['customer' => 'Customers', 'supplier' => 'Suppliers', 'both' => 'Both'] as $value => $label)<option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>@endforeach</select>
            <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Filter</button>
        </form>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Name</th><th class="py-2 px-3 text-left font-medium text-gray-600">Type</th><th class="py-2 px-3 text-left font-medium text-gray-600">Contact</th><th class="py-2 px-3 text-right font-medium text-gray-600">Terms</th><th class="py-2 px-3 text-right font-medium text-gray-600">Credit limit</th></tr></thead>
            <tbody>
            @forelse ($parties as $party)
                <tr class="border-t"><td class="py-2 px-3">{{ $party['code'] }}</td><td class="py-2 px-3"><a href="{{ route('accounting.parties.show', $party['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $party['name'] }}</a>@unless($party['is_active'])<span class="ml-2 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactive</span>@endunless</td><td class="py-2 px-3 capitalize">{{ $party['type'] }}</td><td class="py-2 px-3">{{ $party['email'] ?? $party['phone'] ?? '—' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $party['payment_terms_days'] }} days</td><td class="py-2 px-3 text-right tabular-nums">{{ $party['credit_limit'] === null ? '—' : number_format((float) $party['credit_limit'], 2) }}</td></tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-gray-500">No customers or suppliers yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
