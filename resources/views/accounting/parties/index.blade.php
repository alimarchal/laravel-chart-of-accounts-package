<x-accounting::app-layout title="Customers & Suppliers">
    <x-slot name="header"><x-accounting::page-header title="Customers & Suppliers" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.dashboard')"><x-slot name="actions">@can('parties.create')<a href="{{ route('accounting.parties.create') }}" class="acc-btn acc-btn--primary">New customer or supplier</a>@endcan
                <a href="{{ route('accounting.party-documents.index') }}" class="acc-btn">Invoices &amp; bills</a>
                <a href="{{ route('accounting.party-payments.index') }}" class="acc-btn">Receipts &amp; payments</a>
                <a href="{{ route('accounting.receivables.aging', ['side' => 'receivable']) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Ageing</a></x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::list-filters :action="route('accounting.parties.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'Name, code, e-mail, tax number'],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['customer' => 'Customers', 'supplier' => 'Suppliers', 'both' => 'Both']],
            ['name' => 'active', 'label' => 'Status', 'type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive']],
        ]" />
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
