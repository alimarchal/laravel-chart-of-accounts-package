<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header :title="'Permission '.$permission->name" :createRoute="null" backRoute="settings.permissions.index" />
    </x-slot>

    <div class="py-6"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="bg-white shadow rounded-lg p-5 text-sm space-y-2">
            <p><span class="text-gray-500">Name</span> <span class="font-mono">{{ $permission->name }}</span></p>
            <p><span class="text-gray-500">Guard</span> {{ $permission->guard_name }}</p>
            <p class="text-gray-600">Roles that carry this permission give it to everybody who has the role; a user can also be given it directly (Users, Permissions).</p>
        </div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Roles with this permission</th></tr></thead>
            <tbody>
            @forelse ($permission->roles as $role)
                <tr class="border-t"><td class="py-2 px-3">{{ $role->name }}</td></tr>
            @empty
                <tr><td class="py-6 px-3 text-center text-gray-500">No role carries this permission.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
