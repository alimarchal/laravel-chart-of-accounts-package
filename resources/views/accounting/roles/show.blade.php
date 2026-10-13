<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'Role: '.($role->name)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('settings.roles.index')"></x-accounting::page-header></x-slot>
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
