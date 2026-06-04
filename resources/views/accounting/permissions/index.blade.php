<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Permissions" :createRoute="null" backRoute="accounting.dashboard" />
    </x-slot>

    <x-accounting::filter-section :action="route('settings.permissions.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-accounting::label for="filter_name" value="Name" />
                <x-accounting::input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full" :value="request('filter.name')" />
            </div>
        </div>
    </x-accounting::filter-section>

    <x-accounting::data-table :items="$permissions"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Permission Name'],['label'=>'Roles','align'=>'text-center'],['label'=>'Users','align'=>'text-center']]"
        emptyMessage="No permissions found.">
        @foreach ($permissions as $i => $perm)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $permissions->firstItem() + $i }}</td>
            <td class="py-1 px-2 font-mono text-xs">{{ $perm->name }}</td>
            <td class="py-1 px-2 text-center">{{ $perm->roles_count }}</td>
            <td class="py-1 px-2 text-center">{{ $perm->users_count }}</td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
