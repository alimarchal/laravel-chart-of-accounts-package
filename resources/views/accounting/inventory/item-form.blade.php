<x-accounting::app-layout title="Item">
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item ? 'Edit '.$item['sku'] : 'New item' }}</h2></x-slot>
    @php($v = fn ($name, $default = '') => old($name, $item[$name] ?? $default))
    <div class="py-6"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        @if($locked)<p class="text-sm text-amber-700">Accounts are locked: the item has stock movements.</p>@endif
        <form method="POST" action="{{ $item ? route('accounting.inventory.items.update', $item['id']) : route('accounting.inventory.items.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3">
            @csrf @if($item) @method('PUT') @endif
            @foreach ([['sku', 'SKU', 'text'], ['name', 'Name', 'text'], ['unit', 'Unit', 'text'], ['category', 'Category', 'text'], ['reorder_level', 'Reorder level', 'number']] as [$name, $label, $type])
                <label class="text-sm"><span class="text-gray-700">{{ $label }}</span><input type="{{ $type }}" step="any" name="{{ $name }}" value="{{ $v($name, $name === 'unit' ? 'pcs' : ($name === 'reorder_level' ? '0' : '')) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            @endforeach
            @foreach ([['inventory_account_id', 'Inventory account', ['ASSET']], ['cogs_account_id', 'Cost of goods sold account', ['EXPENSE']]] as [$name, $label, $types])
                <label class="text-sm"><span class="text-gray-700">{{ $label }}</span><select name="{{ $name }}" @disabled($locked) class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($accounts as $account)@if(in_array($account['type'], $types, true))<option value="{{ $account['id'] }}" @selected((string) $v($name) === (string) $account['id'])>{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endif @endforeach</select></label>
            @endforeach
            <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($v('is_active', true))> Active</label>
            <div class="md:col-span-3"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Save</button> <a href="{{ route('accounting.inventory.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Cancel</a></div>
        </form>
    </div></div>
</x-accounting::app-layout>
