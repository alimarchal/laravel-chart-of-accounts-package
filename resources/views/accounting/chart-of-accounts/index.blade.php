<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Chart of Accounts</h2>
            <div class="flex gap-2">
                <a href="{{ route('accounting.chart-of-accounts.tree') }}" class="inline-flex items-center px-4 py-2 bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600 transition">Tree View</a>
                @can('chart-of-accounts.create')
                <a href="{{ route('accounting.chart-of-accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Add Account</a>
                @endcan
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.chart-of-accounts.index')">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div><x-accounting::label for="filter_account_code" value="Account Code" /><x-accounting::input id="filter_account_code" name="filter[account_code]" type="text" class="mt-1 block w-full" :value="request('filter.account_code')" /></div>
            <div><x-accounting::label for="filter_account_name" value="Account Name" /><x-accounting::input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full" :value="request('filter.account_name')" /></div>
            <div><x-accounting::label for="filter_account_type" value="Account Type" />
                <select id="filter_account_type" name="filter[account_type_id]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($accountTypes as $at)<option value="{{ $at->id }}" {{ request('filter.account_type_id') == $at->id ? 'selected' : '' }}>{{ $at->name }}</option>@endforeach
                </select>
            </div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$chartOfAccounts"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Code'],['label'=>'Name'],['label'=>'Type'],['label'=>'Currency','align'=>'text-center'],['label'=>'Normal Balance','align'=>'text-center'],['label'=>'Group','align'=>'text-center'],['label'=>'Active','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No accounts found." :emptyRoute="route('accounting.chart-of-accounts.create')" emptyLinkText="Add one">
        @foreach ($chartOfAccounts as $i => $coa)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $chartOfAccounts->firstItem() + $i }}</td>
            <td class="py-1 px-2 font-mono">{{ $coa->account_code }}</td>
            <td class="py-1 px-2 font-semibold">{{ $coa->account_name }}</td>
            <td class="py-1 px-2">{{ optional($coa->accountType)->name }}</td>
            <td class="py-1 px-2 text-center font-mono">{{ optional($coa->currency)->code }}</td>
            <td class="py-1 px-2 text-center capitalize">{{ $coa->normal_balance }}</td>
            <td class="py-1 px-2 text-center"><span @class(['inline-block w-2 h-2 rounded-full', 'bg-blue-500' => $coa->is_group, 'bg-gray-300' => !$coa->is_group])></span></td>
            <td class="py-1 px-2 text-center"><span @class(['inline-block w-2 h-2 rounded-full', 'bg-green-500' => $coa->is_active, 'bg-red-400' => !$coa->is_active])></span></td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('accounting.chart-of-accounts.show', $coa) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                    @can('chart-of-accounts.update')<a href="{{ route('accounting.chart-of-accounts.edit', $coa) }}" class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:bg-green-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>@endcan
                    @can('chart-of-accounts.delete')<form method="POST" action="{{ route('accounting.chart-of-accounts.destroy', $coa) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>@endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
