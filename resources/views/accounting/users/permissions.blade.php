<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Permissions: {{ $user->name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('settings.users.edit', $user) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                    Edit User
                </a>
                <a href="{{ route('settings.users.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-accounting::status-message class="mb-4 mt-4 shadow-md" />

            {{-- Info: roles summary --}}
            <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-800 font-medium mb-1">Current Roles:</p>
                <div class="flex flex-wrap gap-2">
                    @forelse($user->roles as $role)
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-semibold">{{ $role->name }}</span>
                    @empty
                        <span class="text-gray-500 text-sm">No roles assigned</span>
                    @endforelse
                </div>
                <p class="text-xs text-blue-600 mt-2">
                    <span class="font-semibold">Note:</span>
                    Permissions with <span class="inline-block w-3 h-3 bg-blue-200 border border-blue-400 rounded align-middle"></span> blue highlight are already granted via roles.
                    Direct permissions below <strong>override</strong> role permissions and grant/revoke access specifically for this user.
                </p>
            </div>

            <form method="POST" action="{{ route('settings.users.permissions.sync', $user) }}">
                @csrf

                <div class="space-y-4">
                    @foreach($grouped as $group => $permissions)
                        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="flex items-center justify-between px-6 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                    {{ ucwords(str_replace(['-', '_'], ' ', $group)) }}
                                </h3>
                                <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-gray-500">
                                    <input type="checkbox" class="rounded border-gray-300 group-select-all"
                                        data-group="{{ $group }}"
                                        title="Select all in group" />
                                    Select all
                                </label>
                            </div>
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                @foreach($permissions as $perm)
                                    @php
                                        $isDirect = in_array($perm->name, $directPermissions);
                                        $isViaRole = in_array($perm->name, $rolePermissions);
                                        $action = explode('.', $perm->name)[1] ?? $perm->name;
                                    @endphp
                                    <label class="flex items-center gap-2 cursor-pointer rounded-md px-3 py-2 border
                                        {{ $isViaRole ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200' }}
                                        hover:border-indigo-400 transition group">
                                        <input type="checkbox"
                                            name="permissions[]"
                                            value="{{ $perm->name }}"
                                            {{ $isDirect ? 'checked' : '' }}
                                            data-group="{{ $group }}"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 perm-checkbox" />
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ ucwords(str_replace(['-', '_', '.'], ' ', $action)) }}
                                        </span>
                                        @if($isViaRole)
                                            <span class="ml-auto text-xs text-blue-500 italic" title="Granted via role">via role</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('settings.users.index') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 transition">
                        Cancel
                    </a>
                    <x-accounting::button>
                        Save Permissions
                    </x-accounting::button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelectorAll('.group-select-all').forEach(function (master) {
            const group = master.dataset.group;
            const checkboxes = document.querySelectorAll('.perm-checkbox[data-group="' + group + '"]');

            // Set initial indeterminate/checked state
            const checkedCount = Array.from(checkboxes).filter(c => c.checked).length;
            if (checkedCount === checkboxes.length) {
                master.checked = true;
            } else if (checkedCount > 0) {
                master.indeterminate = true;
            }

            master.addEventListener('change', function () {
                checkboxes.forEach(c => { c.checked = master.checked; });
            });

            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    const noneChecked = Array.from(checkboxes).every(c => !c.checked);
                    master.indeterminate = !allChecked && !noneChecked;
                    master.checked = allChecked;
                });
            });
        });
    </script>
    @endpush
</x-accounting::app-layout>
