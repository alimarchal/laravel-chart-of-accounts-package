@php $accountType = $accountType ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="code" value="Code" />
        <x-accounting::input id="code" type="text" name="code" class="mt-1 block w-full" :value="old('code', optional($accountType)->code)" required />
    </div>
    <div>
        <x-accounting::label for="name" value="Name" />
        <x-accounting::input id="name" type="text" name="name" class="mt-1 block w-full" :value="old('name', optional($accountType)->name)" required />
    </div>
    <div>
        <x-accounting::label for="normal_balance" value="Normal Balance" />
        <select id="normal_balance" name="normal_balance" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Select</option>
            <option value="debit" {{ old('normal_balance', optional($accountType)->normal_balance) === 'debit' ? 'selected' : '' }}>Debit</option>
            <option value="credit" {{ old('normal_balance', optional($accountType)->normal_balance) === 'credit' ? 'selected' : '' }}>Credit</option>
        </select>
    </div>
    <div>
        <x-accounting::label for="report_group" value="Report Group" />
        <select id="report_group" name="report_group" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Select</option>
            <option value="BalanceSheet" {{ old('report_group', optional($accountType)->report_group) === 'BalanceSheet' ? 'selected' : '' }}>Balance Sheet</option>
            <option value="IncomeStatement" {{ old('report_group', optional($accountType)->report_group) === 'IncomeStatement' ? 'selected' : '' }}>Income Statement</option>
        </select>
    </div>
    <div class="md:col-span-2">
        <x-accounting::label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('description', optional($accountType)->description) }}</textarea>
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-gray-300"
            {{ old('is_active', optional($accountType)->is_active ?? true) ? 'checked' : '' }} />
        <x-accounting::label for="is_active" value="Active" />
    </div>
</div>
