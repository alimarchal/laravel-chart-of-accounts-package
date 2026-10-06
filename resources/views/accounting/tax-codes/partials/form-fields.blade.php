@php $taxCode = $taxCode ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="code" value="Code" />
        <x-accounting::input id="code" type="text" name="code" class="mt-1 block w-full" :value="old('code', optional($taxCode)->code)" required />
    </div>
    <div>
        <x-accounting::label for="name" value="Name" />
        <x-accounting::input id="name" type="text" name="name" class="mt-1 block w-full" :value="old('name', optional($taxCode)->name)" required />
    </div>
    <div>
        <x-accounting::label for="kind" value="Kind" />
        <select id="kind" name="kind" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            @foreach (\Alimarchal\LaravelChartOfAccounts\Http\Controllers\TaxCodeController::kindLabels() as $value => $label)
                <option value="{{ $value }}" @selected(old('kind', optional($taxCode)->kind ?? 'output') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-accounting::label for="tax_account_id" value="Tax account" />
        <select id="tax_account_id" name="tax_account_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
            <option value="">None</option>
            @foreach (\Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name']) as $account)
                <option value="{{ $account->id }}" @selected((string) old('tax_account_id', optional($taxCode)->tax_account_id) === (string) $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-accounting::label for="jurisdiction" value="Jurisdiction" />
        <x-accounting::input id="jurisdiction" type="text" name="jurisdiction" class="mt-1 block w-full" :value="old('jurisdiction', optional($taxCode)->jurisdiction)" />
    </div>
    <div class="md:col-span-2">
        <x-accounting::label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('description', optional($taxCode)->description) }}</textarea>
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-gray-300"
            {{ old('is_active', optional($taxCode)->is_active ?? true) ? 'checked' : '' }} />
        <x-accounting::label for="is_active" value="Active" />
    </div>
</div>
