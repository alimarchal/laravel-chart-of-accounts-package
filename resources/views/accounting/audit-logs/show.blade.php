<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Log #{{ $auditLog->id }}</h2>
            <a href="{{ route('accounting.audit-logs.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div><x-accounting::label value="Date" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->created_at->format('Y-m-d H:i:s')" disabled readonly /></div>
                <div><x-accounting::label value="User" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($auditLog->user)->name ?? '—'" disabled readonly /></div>
                <div><x-accounting::label value="Action" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->action" disabled readonly /></div>
                <div><x-accounting::label value="Model Type" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="class_basename($auditLog->auditable_type ?? $auditLog->model_type)" disabled readonly /></div>
                <div><x-accounting::label value="Model ID" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->auditable_id ?? $auditLog->model_id" disabled readonly /></div>
                <div><x-accounting::label value="IP Address" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$auditLog->ip_address ?? '—'" disabled readonly /></div>
            </div>
            @php $changes = $auditLog->old_values ?? $auditLog->changes ?? []; @endphp
            @if ($changes)
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Changes</h3>
                <pre class="bg-gray-50 p-4 rounded text-xs overflow-x-auto">{{ json_encode($changes, JSON_PRETTY_PRINT) }}</pre>
            </div>
            @endif
        </div>
    </div></div>
</x-accounting::app-layout>
