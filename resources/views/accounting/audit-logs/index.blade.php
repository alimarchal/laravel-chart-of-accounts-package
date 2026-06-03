<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Logs</h2>
            <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.audit-logs.index')">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div><x-accounting::label for="filter_model_type" value="Model Type" /><x-accounting::input id="filter_model_type" name="filter[model_type]" type="text" class="mt-1 block w-full" :value="request('filter.model_type')" /></div>
            <div><x-accounting::label for="filter_action" value="Action" /><x-accounting::input id="filter_action" name="filter[action]" type="text" class="mt-1 block w-full" :value="request('filter.action')" /></div>
            <div><x-accounting::label for="filter_date_from" value="Date From" /><x-accounting::input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full" :value="request('filter.date_from')" /></div>
            <div><x-accounting::label for="filter_date_to" value="Date To" /><x-accounting::input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full" :value="request('filter.date_to')" /></div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$auditLogs"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Date'],['label'=>'User'],['label'=>'Action'],['label'=>'Model'],['label'=>'Model ID','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No audit logs found.">
        @foreach ($auditLogs as $i => $log)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $auditLogs->firstItem() + $i }}</td>
            <td class="py-1 px-2 text-xs">{{ $log->created_at->format('Y-m-d H:i') }}</td>
            <td class="py-1 px-2">{{ optional($log->user)->name ?? '—' }}</td>
            <td class="py-1 px-2">
                <span @class(['px-2 py-0.5 rounded text-xs font-medium', 'bg-green-100 text-green-800' => $log->action === 'created', 'bg-yellow-100 text-yellow-800' => $log->action === 'updated', 'bg-red-100 text-red-700' => $log->action === 'deleted'])>{{ $log->action }}</span>
            </td>
            <td class="py-1 px-2 text-xs font-mono">{{ class_basename($log->auditable_type ?? $log->model_type) }}</td>
            <td class="py-1 px-2 text-center text-xs">{{ $log->auditable_id ?? $log->model_id }}</td>
            <td class="py-1 px-2 text-center">
                <a href="{{ route('accounting.audit-logs.show', $log) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
