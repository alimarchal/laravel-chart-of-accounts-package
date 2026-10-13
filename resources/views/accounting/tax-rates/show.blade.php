<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header title="Tax Rate" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.tax-rates.index')"><x-slot name="actions">@can('tax-rates.update')<a href="{{ route('accounting.tax-rates.edit', $taxRate) }}" class="acc-btn acc-btn--dark">Edit</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Tax Code" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($taxRate->taxCode)->name" disabled readonly /></div>
                <div><x-accounting::label value="Rate %" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($taxRate->rate, 2).'%'" disabled readonly /></div>
                <div><x-accounting::label value="Effective From" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($taxRate->effective_from)->format('Y-m-d')" disabled readonly /></div>
                <div><x-accounting::label value="Effective To" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($taxRate->effective_to)->format('Y-m-d') ?? '—'" disabled readonly /></div>
                <div><x-accounting::label value="Active" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$taxRate->is_active ? 'Yes' : 'No'" disabled readonly /></div>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
