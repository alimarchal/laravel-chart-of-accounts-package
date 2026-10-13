<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'Bank Account: '.($bankAccount->account_name)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.bank-accounts.index')"><x-slot name="actions">@can('bank-accounts.update')<a href="{{ route('accounting.bank-accounts.edit', $bankAccount) }}" class="acc-btn acc-btn--dark">Edit</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Account Name" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$bankAccount->account_name" disabled readonly /></div>
                <div><x-accounting::label value="Account Number" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$bankAccount->account_number" disabled readonly /></div>
                <div><x-accounting::label value="Bank Name" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$bankAccount->bank_name" disabled readonly /></div>
                <div><x-accounting::label value="Branch" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$bankAccount->branch" disabled readonly /></div>
                <div><x-accounting::label value="IBAN" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$bankAccount->iban" disabled readonly /></div>
                <div><x-accounting::label value="SWIFT Code" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$bankAccount->swift_code" disabled readonly /></div>
                <div><x-accounting::label value="Active" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$bankAccount->is_active ? 'Yes' : 'No'" disabled readonly /></div>
                <div class="md:col-span-2"><x-accounting::label value="Description" /><textarea class="border-gray-300 rounded-md shadow-sm block mt-1 w-full bg-gray-100" rows="3" disabled readonly>{{ $bankAccount->description }}</textarea></div>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
