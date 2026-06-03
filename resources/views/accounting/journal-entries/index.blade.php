<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Journal Entries" :createRoute="route('accounting.journal-entries.create')" createLabel="New Entry" createPermission="journal-entries.create" backRoute="accounting.dashboard" :showSearch="true" :showRefresh="true" />
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.journal-entries.index')">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div><x-accounting::label for="filter_reference" value="Reference" /><x-accounting::input id="filter_reference" name="filter[reference]" type="text" class="mt-1 block w-full" :value="request('filter.reference')" /></div>
            <div><x-accounting::label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="draft" {{ request('filter.status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="posted" {{ request('filter.status') === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="voided" {{ request('filter.status') === 'voided' ? 'selected' : '' }}>Voided</option>
                </select>
            </div>
            <div><x-accounting::label for="filter_date_from" value="Date From" /><x-accounting::input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full" :value="request('filter.date_from')" /></div>
            <div><x-accounting::label for="filter_date_to" value="Date To" /><x-accounting::input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full" :value="request('filter.date_to')" /></div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$journalEntries"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Date'],['label'=>'Reference'],['label'=>'Description'],['label'=>'Status','align'=>'text-center'],['label'=>'Lines','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No journal entries found." :emptyRoute="route('accounting.journal-entries.create')" emptyLinkText="Create one">
        @foreach ($journalEntries as $i => $je)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $journalEntries->firstItem() + $i }}</td>
            <td class="py-1 px-2">{{ $je->entry_date->format('Y-m-d') }}</td>
            <td class="py-1 px-2 font-mono">{{ $je->reference }}</td>
            <td class="py-1 px-2">{{ Str::limit($je->description, 50) }}</td>
            <td class="py-1 px-2 text-center">
                <span @class(['px-2 py-0.5 rounded text-xs font-medium', 'bg-yellow-100 text-yellow-800' => $je->status === 'draft', 'bg-green-100 text-green-800' => $je->status === 'posted', 'bg-red-100 text-red-700' => $je->status === 'voided'])>{{ ucfirst($je->status) }}</span>
            </td>
            <td class="py-1 px-2 text-center">{{ $je->lines_count ?? $je->lines->count() }}</td>
            <td class="py-1 px-2 text-center">
                <a href="{{ route('accounting.journal-entries.show', $je) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
