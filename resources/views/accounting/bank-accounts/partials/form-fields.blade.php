@php $bankAccount = $bankAccount ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="account_name" value="Account Name" />
        <x-accounting::input id="account_name" type="text" name="account_name" class="mt-1 block w-full" :value="old('account_name', optional($bankAccount)->account_name)" required />
    </div>
    <div>
        <x-accounting::label for="account_number" value="Account Number" />
        <x-accounting::input id="account_number" type="text" name="account_number" class="mt-1 block w-full" :value="old('account_number', optional($bankAccount)->account_number)" required />
    </div>
    <div>
        <x-accounting::label for="bank_name" value="Bank Name" />
        <x-accounting::input id="bank_name" type="text" name="bank_name" class="mt-1 block w-full" :value="old('bank_name', optional($bankAccount)->bank_name)" required />
    </div>
    <div>
        <x-accounting::label for="branch" value="Branch" />
        <x-accounting::input id="branch" type="text" name="branch" class="mt-1 block w-full" :value="old('branch', optional($bankAccount)->branch)" />
    </div>
    <div>
        <x-accounting::label for="iban" value="IBAN" />
        <x-accounting::input id="iban" type="text" name="iban" class="mt-1 block w-full" :value="old('iban', optional($bankAccount)->iban)" />
    </div>
    <div>
        <x-accounting::label for="swift_code" value="SWIFT Code" />
        <x-accounting::input id="swift_code" type="text" name="swift_code" class="mt-1 block w-full" :value="old('swift_code', optional($bankAccount)->swift_code)" />
    </div>
    <div class="md:col-span-2">
        <x-accounting::label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('description', optional($bankAccount)->description) }}</textarea>
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-gray-300"
            {{ old('is_active', optional($bankAccount)->is_active ?? true) ? 'checked' : '' }} />
        <x-accounting::label for="is_active" value="Active" />
    </div>
</div>
