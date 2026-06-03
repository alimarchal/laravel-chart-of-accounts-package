<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Account Types" :createRoute="route('accounting.account-types.create')" createLabel="Add Type" createPermission="account-types.create" backRoute="accounting.dashboard" />
    </x-slot>

    <x-accounting::filter-section :action="route('accounting.account-types.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-accounting::label for="filter_name" value="Name" />
                <x-accounting::input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full" :value="request('filter.name')" />
            </div>
            <div>
                <x-accounting::label for="filter_code" value="Code" />
                <x-accounting::input id="filter_code" name="filter[code]" type="text" class="mt-1 block w-full" :value="request('filter.code')" />
            </div>
        </div>
    </x-accounting::filter-section>

    <x-accounting::data-table :items="$accountTypes"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Code'],['label'=>'Name'],['label'=>'Normal Balance','align'=>'text-center'],['label'=>'Report Group','align'=>'text-center'],['label'=>'Active','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No account types found." :emptyRoute="route('accounting.account-types.create')" emptyLinkText="Add one">
        @foreach ($accountTypes as $i => $at)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $accountTypes->firstItem() + $i }}</td>
            <td class="py-1 px-2">{{ $at->code }}</td>
            <td class="py-1 px-2 font-semibold">{{ $at->name }}</td>
            <td class="py-1 px-2 text-center capitalize">{{ $at->normal_balance }}</td>
            <td class="py-1 px-2 text-center">{{ $at->report_group }}</td>
            <td class="py-1 px-2 text-center">
                <span @class(['inline-block w-2 h-2 rounded-full', 'bg-green-500' => $at->is_active, 'bg-red-400' => !$at->is_active])></span>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('accounting.account-types.show', $at) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </a>
                    @can('account-types.update')
                    <a href="{{ route('accounting.account-types.edit', $at) }}" class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:bg-green-100 rounded-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>
                    @endcan
                    @can('account-types.delete')
                    <form method="POST" action="{{ route('accounting.account-types.destroy', $at) }}" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-100 rounded-md">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                    @endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
