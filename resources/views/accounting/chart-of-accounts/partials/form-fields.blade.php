@php $chartOfAccount = $chartOfAccount ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="account_code" value="Account Code" />
        <x-accounting::input id="account_code" type="text" name="account_code" class="mt-1 block w-full" :value="old('account_code', optional($chartOfAccount)->account_code)" required />
    </div>
    <div>
        <x-accounting::label for="account_name" value="Account Name" />
        <x-accounting::input id="account_name" type="text" name="account_name" class="mt-1 block w-full" :value="old('account_name', optional($chartOfAccount)->account_name)" required />
    </div>
    <div>
        <x-accounting::label for="account_type_id" value="Account Type" />
        <select id="account_type_id" name="account_type_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Select Type</option>
            @foreach ($accountTypes as $at)
            <option value="{{ $at->id }}" {{ old('account_type_id', optional($chartOfAccount)->account_type_id) == $at->id ? 'selected' : '' }}>{{ $at->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-accounting::label for="currency_id" value="Currency" />
        <select id="currency_id" name="currency_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Select Currency</option>
            @foreach ($currencies as $cur)
            <option value="{{ $cur->id }}" {{ old('currency_id', optional($chartOfAccount)->currency_id) == $cur->id ? 'selected' : '' }}>{{ $cur->code }} - {{ $cur->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-accounting::label for="parent_id" value="Parent Account" />
        <select id="parent_id" name="parent_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">None (Top Level)</option>
            @foreach ($parentAccounts as $pa)
            <option value="{{ $pa->id }}" {{ old('parent_id', optional($chartOfAccount)->parent_id) == $pa->id ? 'selected' : '' }}>{{ $pa->account_code }} - {{ $pa->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-accounting::label for="normal_balance" value="Normal Balance" />
        <select id="normal_balance" name="normal_balance" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Select</option>
            <option value="debit" {{ old('normal_balance', optional($chartOfAccount)->normal_balance) === 'debit' ? 'selected' : '' }}>Debit</option>
            <option value="credit" {{ old('normal_balance', optional($chartOfAccount)->normal_balance) === 'credit' ? 'selected' : '' }}>Credit</option>
        </select>
    </div>
    <div class="md:col-span-2">
        <x-accounting::label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('description', optional($chartOfAccount)->description) }}</textarea>
    </div>
    <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_group" name="is_group" value="1" class="rounded border-gray-300"
                {{ old('is_group', optional($chartOfAccount)->is_group) ? 'checked' : '' }} />
            <x-accounting::label for="is_group" value="Group Account" />
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-gray-300"
                {{ old('is_active', optional($chartOfAccount)->is_active ?? true) ? 'checked' : '' }} />
            <x-accounting::label for="is_active" value="Active" />
        </div>
    </div>
</div>
