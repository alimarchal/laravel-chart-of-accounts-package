<x-accounting::app-layout title="Budgets">
    <x-slot name="header"><x-accounting::page-header title="Budgets" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.dashboard')"><x-slot name="actions">@can('budgets.create')<a href="{{ route('accounting.budgets.create') }}" class="acc-btn acc-btn--primary">New budget</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    @php($styles = ['draft' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-800', 'closed' => 'bg-gray-100 text-gray-600'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <p class="text-sm text-gray-600">Plan income and expenses by account and month, then follow budget against actual.</p>
        <x-accounting::list-filters :action="route('accounting.budgets.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'Name or notes'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'approved' => 'Approved', 'closed' => 'Closed']],
            ['name' => 'date_from', 'label' => 'Starts from', 'type' => 'date'],
            ['name' => 'date_to', 'label' => 'Ends by', 'type' => 'date'],
        ]" />
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Name</th><th class="py-2 px-3 text-left font-medium text-gray-600">Period</th><th class="py-2 px-3 text-right font-medium text-gray-600">Amounts</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th></tr></thead>
            <tbody>
            @forelse ($budgets as $budget)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.budgets.show', $budget['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $budget['name'] }}</a></td><td class="py-2 px-3">{{ $budget['start_date'] }} → {{ $budget['end_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $budget['lines_count'] }}</td><td class="py-2 px-3"><x-accounting::status-pill :status="$budget['status']" /></td></tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-gray-500">No budgets yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
