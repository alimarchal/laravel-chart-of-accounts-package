<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Role: {{ $role->name }}</h2>
            <a href="{{ route('settings.roles.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Permissions for "{{ $role->name }}"</h3>
            @php $grouped = $role->permissions->groupBy(fn ($p) => explode('.', $p->name)[0]); @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($grouped as $group => $perms)
                <div class="bg-gray-50 rounded-lg p-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ $group }}</h4>
                    @foreach($perms as $perm)
                        <span class="block text-sm text-gray-700 py-0.5">{{ $perm->name }}</span>
                    @endforeach
                </div>
                @endforeach
            </div>
            <div class="mt-6">
                <a href="{{ route('settings.roles.edit', $role) }}" class="inline-flex items-center px-4 py-2 bg-green-700 text-white rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-green-800">Edit Role</a>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
