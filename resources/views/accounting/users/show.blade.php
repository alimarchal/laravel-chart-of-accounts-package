<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">User: {{ $user->name }}</h2>
            <a href="{{ route('settings.users.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="text-sm font-medium text-gray-500">Name</dt><dd class="mt-1 text-gray-900">{{ $user->name }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500">Email</dt><dd class="mt-1 text-gray-900">{{ $user->email }}</dd></div>
                <div class="md:col-span-2"><dt class="text-sm font-medium text-gray-500 mb-2">Roles</dt>
                    <dd class="flex flex-wrap gap-2">
                        @forelse($user->roles as $role)
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm">{{ $role->name }}</span>
                        @empty <span class="text-gray-400 text-sm">No roles assigned</span>
                        @endforelse
                    </dd>
                </div>
                <div class="md:col-span-2"><dt class="text-sm font-medium text-gray-500 mb-2">Direct Permissions</dt>
                    <dd class="flex flex-wrap gap-2">
                        @forelse($user->permissions as $perm)
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">{{ $perm->name }}</span>
                        @empty <span class="text-gray-400 text-sm">No direct permissions</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
            @can('user.update')
            <div class="mt-6 flex gap-2">
                <a href="{{ route('settings.users.edit', $user) }}" class="inline-flex items-center px-4 py-2 bg-green-700 text-white rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-green-800">Edit</a>
            </div>
            @endcan
        </div>
    </div></div>
</x-accounting::app-layout>
