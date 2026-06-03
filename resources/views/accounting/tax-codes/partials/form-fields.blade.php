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
