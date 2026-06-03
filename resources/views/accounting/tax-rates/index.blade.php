<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Tax Rates" :createRoute="route('accounting.tax-rates.create')" createLabel="Add Tax Rate" createPermission="tax-rates.create" backRoute="accounting.dashboard" />
    </x-slot>
    <x-accounting::filter-section :action="route('accounting.tax-rates.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><x-accounting::label for="filter_tax_code" value="Tax Code" /><x-accounting::input id="filter_tax_code" name="filter[tax_code]" type="text" class="mt-1 block w-full" :value="request('filter.tax_code')" /></div>
        </div>
    </x-accounting::filter-section>
    <x-accounting::data-table :items="$taxRates"
        :headers="[['label'=>'#','align'=>'text-center'],['label'=>'Tax Code'],['label'=>'Rate %','align'=>'text-right'],['label'=>'Effective From'],['label'=>'Effective To'],['label'=>'Active','align'=>'text-center'],['label'=>'Actions','align'=>'text-center']]"
        emptyMessage="No tax rates found." :emptyRoute="route('accounting.tax-rates.create')" emptyLinkText="Add one">
        @foreach ($taxRates as $i => $tr)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">{{ $taxRates->firstItem() + $i }}</td>
            <td class="py-1 px-2">{{ optional($tr->taxCode)->name }}</td>
            <td class="py-1 px-2 text-right">{{ number_format($tr->rate, 2) }}%</td>
            <td class="py-1 px-2">{{ optional($tr->effective_from)->format('Y-m-d') }}</td>
            <td class="py-1 px-2">{{ optional($tr->effective_to)->format('Y-m-d') ?? '—' }}</td>
            <td class="py-1 px-2 text-center"><span @class(['inline-block w-2 h-2 rounded-full', 'bg-green-500' => $tr->is_active, 'bg-red-400' => !$tr->is_active])></span></td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('accounting.tax-rates.show', $tr) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:bg-blue-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                    @can('tax-rates.update')<a href="{{ route('accounting.tax-rates.edit', $tr) }}" class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:bg-green-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>@endcan
                    @can('tax-rates.delete')<form method="POST" action="{{ route('accounting.tax-rates.destroy', $tr) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>@endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-accounting::data-table>
</x-accounting::app-layout>
