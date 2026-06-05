<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Audit Logs" :showSearch="true" :showRefresh="true" backRoute="accounting.dashboard" />
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.audit-logs.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-accounting::label for="filter_table_name" value="Table / Model" />
                <select id="filter_table_name" name="filter[table_name]" class="select2 border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Tables</option>
                    @foreach ($tableNames as $t)
                        <option value="{{ $t }}" {{ request('filter.table_name') === $t ? 'selected' : '' }}>{{ str_replace('accounting_', '', $t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-accounting::label for="filter_action" value="Action" />
                <select id="filter_action" name="filter[action]" class="select2 border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Actions</option>
                    <option value="INSERT" {{ request('filter.action') === 'INSERT' ? 'selected' : '' }}>Insert (Create)</option>
                    <option value="UPDATE" {{ request('filter.action') === 'UPDATE' ? 'selected' : '' }}>Update</option>
                    <option value="DELETE" {{ request('filter.action') === 'DELETE' ? 'selected' : '' }}>Delete</option>
                </select>
            </div>
            <div>
                <x-accounting::label for="filter_date_from" value="Date From" />
                <x-accounting::input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full" :value="request('filter.date_from')" />
            </div>
            <div>
                <x-accounting::label for="filter_date_to" value="Date To" />
                <x-accounting::input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full" :value="request('filter.date_to')" />
            </div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$auditLogs"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Date'],['label'=>'User'],['label'=>'Action'],['label'=>'Table'],['label'=>'Record ID','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No audit logs found.">
        @foreach ($auditLogs as $i => $log)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $auditLogs->firstItem() + $i }}</td>
            <td class="py-1 px-2 text-xs">{{ $log->created_at->format('Y-m-d H:i') }}</td>
            <td class="py-1 px-2">{{ optional($log->user)->name ?? '—' }}</td>
            <td class="py-1 px-2">
                <span @class(['px-2 py-0.5 rounded text-xs font-medium',
                    'bg-green-100 text-green-800' => in_array(strtoupper($log->action), ['INSERT', 'created']),
                    'bg-yellow-100 text-yellow-800' => in_array(strtoupper($log->action), ['UPDATE', 'updated']),
                    'bg-red-100 text-red-700' => in_array(strtoupper($log->action), ['DELETE', 'deleted'])])>
                    {{ $log->action }}
                </span>
            </td>
            <td class="py-1 px-2 text-xs font-mono">{{ str_replace('accounting_', '', $log->table_name ?? class_basename($log->auditable_type ?? $log->model_type ?? '—')) }}</td>
            <td class="py-1 px-2 text-center text-xs">{{ $log->record_id ?? $log->auditable_id ?? $log->model_id ?? '—' }}</td>
            <td class="py-1 px-2 text-center">
                <a href="{{ route('accounting.audit-logs.show', $log) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
