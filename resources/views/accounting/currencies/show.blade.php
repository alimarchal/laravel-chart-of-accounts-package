<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'Currency: '.($currency->name)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.currencies.index')"><x-slot name="actions">@can('currencies.update')<a href="{{ route('accounting.currencies.edit', $currency) }}" class="acc-btn acc-btn--dark">Edit</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Code" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$currency->code" disabled readonly /></div>
                <div><x-accounting::label value="Name" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$currency->name" disabled readonly /></div>
                <div><x-accounting::label value="Symbol" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$currency->symbol" disabled readonly /></div>
                <div><x-accounting::label value="Exchange Rate" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$currency->exchange_rate_to_base" disabled readonly /></div>
                <div><x-accounting::label value="Base Currency" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$currency->is_base ? 'Yes' : 'No'" disabled readonly /></div>
                <div><x-accounting::label value="Active" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$currency->is_active ? 'Yes' : 'No'" disabled readonly /></div>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
