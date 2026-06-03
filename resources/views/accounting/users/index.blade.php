<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Users" :createRoute="route('settings.users.create')" createLabel="Add User" createPermission="user.create" backRoute="settings.dashboard" />
    </x-slot>

    <x-accounting::filter-section :action="route('settings.users.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-accounting::label for="filter_name" value="Name" />
                <x-accounting::input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full" :value="request('filter.name')" />
            </div>
            <div>
                <x-accounting::label for="filter_email" value="Email" />
                <x-accounting::input id="filter_email" name="filter[email]" type="text" class="mt-1 block w-full" :value="request('filter.email')" />
            </div>
            <div>
                <x-accounting::label for="filter_role" value="Role" />
                <select id="filter_role" name="filter[role]" class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ request('filter.role') == $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-accounting::filter-section>

    <x-accounting::data-table :items="$users"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Name'],['label'=>'Email'],['label'=>'Roles'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No users found." :emptyRoute="route('settings.users.create')" emptyLinkText="Add one">
        @foreach ($users as $i => $user)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $users->firstItem() + $i }}</td>
            <td class="py-1 px-2 font-semibold">{{ $user->name }}</td>
            <td class="py-1 px-2 text-gray-600">{{ $user->email }}</td>
            <td class="py-1 px-2">
                <div class="flex flex-wrap gap-1">
                    @foreach($user->roles as $role)
                        <span class="inline-block px-2 py-0.5 text-xs bg-indigo-100 text-indigo-700 rounded-full">{{ $role->name }}</span>
                    @endforeach
                </div>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('settings.users.show', $user) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </a>
                    @can('user.update')
                    <a href="{{ route('settings.users.edit', $user) }}" class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:bg-green-100 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    @endcan
                    @can('user.delete')
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('confirm-delete', { detail: { url: '{{ route('settings.users.destroy', $user) }}' } }))" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-100 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                    @endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
    <x-accounting::delete-modal />
</x-accounting::app-layout>
