<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Branches" :createRoute="route('accounting.branches.create')" createLabel="Add Branch" createPermission="branches.create" backRoute="accounting.dashboard" />
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.branches.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div><x-accounting::label for="filter_code" value="Code" /><x-accounting::input id="filter_code" name="filter[code]" type="text" class="mt-1 block w-full" :value="request('filter.code')" /></div>
            <div><x-accounting::label for="filter_name" value="Name" /><x-accounting::input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full" :value="request('filter.name')" /></div>
            <div><x-accounting::label for="filter_city" value="City" /><x-accounting::input id="filter_city" name="filter[city]" type="text" class="mt-1 block w-full" :value="request('filter.city')" /></div>
            <div><x-accounting::label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]" class="select2 border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('filter.is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$branches"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Code'],['label'=>'Name'],['label'=>'City'],['label'=>'Phone'],['label'=>'Head office','align'=>'text-center'],['label'=>'Active','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No branches yet." :emptyRoute="route('accounting.branches.create')" emptyLinkText="Add the first branch">
        @foreach ($branches as $i => $branch)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $branches->firstItem() + $i }}</td>
            <td class="py-1 px-2 font-mono">{{ $branch->code }}</td>
            <td class="py-1 px-2 font-semibold">{{ $branch->name }}</td>
            <td class="py-1 px-2">{{ $branch->city }}</td>
            <td class="py-1 px-2">{{ $branch->phone }}</td>
            <td class="py-1 px-2 text-center">{{ $branch->is_head_office ? 'Yes' : '' }}</td>
            <td class="py-1 px-2 text-center"><span @class(['inline-block w-2 h-2 rounded-full', 'bg-green-500' => $branch->is_active, 'bg-red-400' => !$branch->is_active])></span></td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    @can('branches.update')<a href="{{ route('accounting.branches.edit', $branch) }}" title="Edit" class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:bg-green-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>@endcan
                    @can('branches.delete')<form method="POST" action="{{ route('accounting.branches.destroy', $branch) }}" onsubmit="return confirm('Delete this branch?')">@csrf @method('DELETE')<button type="submit" title="Delete" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>@endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
