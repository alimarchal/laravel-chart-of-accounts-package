@php $currency = $currency ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="code" value="Code (3 chars)" />
        <x-accounting::input id="code" type="text" name="code" class="mt-1 block w-full" maxlength="3" :value="old('code', optional($currency)->code)" required />
    </div>
    <div>
        <x-accounting::label for="name" value="Name" />
        <x-accounting::input id="name" type="text" name="name" class="mt-1 block w-full" :value="old('name', optional($currency)->name)" required />
    </div>
    <div>
        <x-accounting::label for="symbol" value="Symbol" />
        <x-accounting::input id="symbol" type="text" name="symbol" class="mt-1 block w-full" :value="old('symbol', optional($currency)->symbol)" required />
    </div>
    <div>
        <x-accounting::label for="exchange_rate_to_base" value="Exchange Rate to Base" />
        <x-accounting::input id="exchange_rate_to_base" type="number" step="0.00000001" min="0" name="exchange_rate_to_base" class="mt-1 block w-full" :value="old('exchange_rate_to_base', optional($currency)->exchange_rate_to_base)" required />
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" id="is_base" name="is_base" value="1" class="rounded border-gray-300"
            {{ old('is_base', optional($currency)->is_base) ? 'checked' : '' }} />
        <x-accounting::label for="is_base" value="Base Currency" />
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-gray-300"
            {{ old('is_active', optional($currency)->is_active ?? true) ? 'checked' : '' }} />
        <x-accounting::label for="is_active" value="Active" />
    </div>
</div>
