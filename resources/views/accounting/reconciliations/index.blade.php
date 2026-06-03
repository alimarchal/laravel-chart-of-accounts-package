<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Reconciliations" :createRoute="route('accounting.reconciliations.create')" createLabel="New Reconciliation" createPermission="reconciliations.create" backRoute="accounting.dashboard" />
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.reconciliations.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><x-accounting::label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="pending" {{ request('filter.status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ request('filter.status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('filter.status') === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <div><x-accounting::label for="filter_date_from" value="Date From" /><x-accounting::input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full" :value="request('filter.date_from')" /></div>
            <div><x-accounting::label for="filter_date_to" value="Date To" /><x-accounting::input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full" :value="request('filter.date_to')" /></div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$reconciliations"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Bank Account'],['label'=>'Statement Date'],['label'=>'Statement Balance','align'=>'text-right'],['label'=>'Book Balance','align'=>'text-right'],['label'=>'Status','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No reconciliations found." :emptyRoute="route('accounting.reconciliations.create')" emptyLinkText="Create one">
        @foreach ($reconciliations as $i => $r)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $reconciliations->firstItem() + $i }}</td>
            <td class="py-1 px-2">{{ optional($r->bankAccount)->account_name }}</td>
            <td class="py-1 px-2">{{ $r->statement_date->format('Y-m-d') }}</td>
            <td class="py-1 px-2 text-right font-mono">{{ number_format($r->statement_balance, 2) }}</td>
            <td class="py-1 px-2 text-right font-mono">{{ number_format($r->book_balance, 2) }}</td>
            <td class="py-1 px-2 text-center">
                <span @class(['px-2 py-0.5 rounded text-xs font-medium', 'bg-yellow-100 text-yellow-800' => $r->status === 'pending', 'bg-blue-100 text-blue-800' => $r->status === 'in_progress', 'bg-green-100 text-green-800' => $r->status === 'completed'])>{{ ucfirst(str_replace('_', ' ', $r->status)) }}</span>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('accounting.reconciliations.show', $r) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                    @can('reconciliations.update')<a href="{{ route('accounting.reconciliations.edit', $r) }}" class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:bg-green-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>@endcan
                    @can('reconciliations.delete')<form method="POST" action="{{ route('accounting.reconciliations.destroy', $r) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>@endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
