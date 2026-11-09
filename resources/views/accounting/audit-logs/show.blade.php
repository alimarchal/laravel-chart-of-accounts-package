<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'Audit Log #'.($auditLog->id)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.audit-logs.index')"></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div><x-accounting::label value="Date" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->created_at->format('Y-m-d H:i:s')" disabled readonly /></div>
                <div><x-accounting::label value="User" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($auditLog->user)->name ?? '—'" disabled readonly /></div>
                <div><x-accounting::label value="Action" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->action" disabled readonly /></div>
                <div><x-accounting::label value="Table" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="str_replace('accounting_', '', $auditLog->table_name)" disabled readonly /></div>
                <div><x-accounting::label value="Record ID" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->record_id" disabled readonly /></div>
                <div><x-accounting::label value="IP Address" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->ip_address ?? '—'" disabled readonly /></div>
            </div>
            @foreach (['old_values' => 'Before', 'new_values' => 'After'] as $field => $title)
            @if ($auditLog->{$field})
            <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ $title }}</h3>
                <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">{{ json_encode($auditLog->{$field}, JSON_PRETTY_PRINT) }}</pre>
            </div>
            @endif
            @endforeach
        </div>
    </div></div>
</x-accounting::app-layout>
