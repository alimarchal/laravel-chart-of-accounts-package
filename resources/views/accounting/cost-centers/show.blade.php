<x-accounting::app-layout>
    <x-slot name="header"><x-accounting::page-header :title="'Cost Center: '.($costCenter->name)" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.cost-centers.index')"><x-slot name="actions">@can('cost-centers.update')<a href="{{ route('accounting.cost-centers.edit', $costCenter) }}" class="acc-btn acc-btn--dark">Edit</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Code" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$costCenter->code" disabled readonly /></div>
                <div><x-accounting::label value="Name" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$costCenter->name" disabled readonly /></div>
                <div><x-accounting::label value="Type" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="str_replace('_',' ',$costCenter->type)" disabled readonly /></div>
                <div><x-accounting::label value="Active" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$costCenter->is_active ? 'Yes' : 'No'" disabled readonly /></div>
                <div class="md:col-span-2"><x-accounting::label value="Description" /><textarea class="border-gray-300 rounded-md shadow-sm block mt-1 w-full bg-gray-100" rows="3" disabled readonly>{{ $costCenter->description }}</textarea></div>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
