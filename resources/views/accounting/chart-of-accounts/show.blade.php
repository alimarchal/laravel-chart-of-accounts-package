<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="($chartOfAccount->account_code).' — '.($chartOfAccount->account_name)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.chart-of-accounts.index')"><x-slot name="actions">@can('chart-of-accounts.update')<a href="{{ route('accounting.chart-of-accounts.edit', $chartOfAccount) }}" class="acc-btn acc-btn--dark">Edit</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Account Code" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$chartOfAccount->account_code" disabled readonly /></div>
                <div><x-accounting::label value="Account Name" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$chartOfAccount->account_name" disabled readonly /></div>
                <div><x-accounting::label value="Account Type" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($chartOfAccount->accountType)->name" disabled readonly /></div>
                <div><x-accounting::label value="Currency" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($chartOfAccount->currency)->code" disabled readonly /></div>
                <div><x-accounting::label value="Normal Balance" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100 capitalize" :value="$chartOfAccount->normal_balance" disabled readonly /></div>
                <div><x-accounting::label value="Parent Account" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($chartOfAccount->parent)->account_name ?? '—'" disabled readonly /></div>
                <div><x-accounting::label value="Group Account" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$chartOfAccount->is_group ? 'Yes' : 'No'" disabled readonly /></div>
                <div><x-accounting::label value="Active" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$chartOfAccount->is_active ? 'Yes' : 'No'" disabled readonly /></div>
                <div class="md:col-span-2"><x-accounting::label value="Description" /><textarea class="border-gray-300 rounded-md shadow-sm block mt-1 w-full bg-gray-100" rows="3" disabled readonly>{{ $chartOfAccount->description }}</textarea></div>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
