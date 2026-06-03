<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Account Balance Snapshots</h2>
            <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.account-balance-snapshots.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><x-accounting::label for="filter_period_id" value="Period" />
                <select id="filter_period_id" name="filter[period_id]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Periods</option>
                    @foreach ($periods as $p)<option value="{{ $p->id }}" {{ request('filter.period_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div><x-accounting::label for="filter_account_code" value="Account Code" /><x-accounting::input id="filter_account_code" name="filter[account_code]" type="text" class="mt-1 block w-full" :value="request('filter.account_code')" /></div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$snapshots"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Period'],['label'=>'Account Code'],['label'=>'Account Name'],['label'=>'Opening Balance','align'=>'text-right'],['label'=>'Closing Balance','align'=>'text-right'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No snapshots found.">
        @foreach ($snapshots as $i => $snap)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $snapshots->firstItem() + $i }}</td>
            <td class="py-1 px-2">{{ optional($snap->period)->name }}</td>
            <td class="py-1 px-2 font-mono">{{ optional($snap->account)->account_code }}</td>
            <td class="py-1 px-2">{{ optional($snap->account)->account_name }}</td>
            <td class="py-1 px-2 text-right font-mono">{{ number_format($snap->opening_balance, 2) }}</td>
            <td class="py-1 px-2 text-right font-mono">{{ number_format($snap->closing_balance, 2) }}</td>
            <td class="py-1 px-2 text-center">
                <a href="{{ route('accounting.account-balance-snapshots.show', $snap) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
