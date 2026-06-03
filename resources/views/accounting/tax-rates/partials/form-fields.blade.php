@php $taxRate = $taxRate ?? null; @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-accounting::label for="tax_code_id" value="Tax Code" />
        <select id="tax_code_id" name="tax_code_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
            <option value="">Select Tax Code</option>
            @foreach ($taxCodes as $code)
            <option value="{{ $code->id }}" {{ old('tax_code_id', optional($taxRate)->tax_code_id) == $code->id ? 'selected' : '' }}>{{ $code->code }} - {{ $code->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-accounting::label for="rate" value="Rate (%)" />
        <x-accounting::input id="rate" type="number" step="0.01" min="0" max="100" name="rate" class="mt-1 block w-full" :value="old('rate', optional($taxRate)->rate)" required />
    </div>
    <div>
        <x-accounting::label for="effective_from" value="Effective From" />
        <x-accounting::input id="effective_from" type="date" name="effective_from" class="mt-1 block w-full" :value="old('effective_from', optional($taxRate)->effective_from?->format('Y-m-d'))" required />
    </div>
    <div>
        <x-accounting::label for="effective_to" value="Effective To" />
        <x-accounting::input id="effective_to" type="date" name="effective_to" class="mt-1 block w-full" :value="old('effective_to', optional($taxRate)->effective_to?->format('Y-m-d'))" />
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-gray-300"
            {{ old('is_active', optional($taxRate)->is_active ?? true) ? 'checked' : '' }} />
        <x-accounting::label for="is_active" value="Active" />
    </div>
</div>
