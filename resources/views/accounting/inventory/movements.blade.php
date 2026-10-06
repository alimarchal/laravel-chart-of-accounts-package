<x-accounting::app-layout title="Stock movements">
    <x-slot name="header">
        <div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Stock movements</h2>
            <div class="flex gap-2">@can('inventory.move')<a href="{{ route('accounting.inventory.movements.create') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Move stock</a>@endcan<a href="{{ route('accounting.inventory.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Inventory</a></div></div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.'))
    @php($itemName = collect($items)->pluck('sku', 'id'))
    @php($whName = collect($warehouses)->pluck('name', 'id'))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <select name="item_id" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All items</option>@foreach ($items as $item)<option value="{{ $item['id'] }}" @selected((string) $filters['item_id'] === (string) $item['id'])>{{ $item['sku'] }}</option>@endforeach</select>
            <select name="warehouse_id" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All warehouses</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse['id'] }}" @selected((string) $filters['warehouse_id'] === (string) $warehouse['id'])>{{ $warehouse['name'] }}</option>@endforeach</select>
            <select name="type" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All types</option>@foreach (['receipt', 'issue', 'adjustment', 'transfer_in', 'transfer_out'] as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ str_replace('_', ' ', $type) }}</option>@endforeach</select>
            <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Filter</button>
        </form>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Type</th><th class="py-2 px-3 text-left font-medium text-gray-600">Item</th><th class="py-2 px-3 text-left font-medium text-gray-600">Warehouse</th><th class="py-2 px-3 text-right font-medium text-gray-600">Quantity</th><th class="py-2 px-3 text-right font-medium text-gray-600">Unit cost</th><th class="py-2 px-3 text-right font-medium text-gray-600">Value</th><th class="py-2 px-3 text-left font-medium text-gray-600">Reference</th><th class="py-2 px-3 text-right font-medium text-gray-600">Entry</th></tr></thead>
            <tbody>
            @forelse ($movements as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['date'] }}</td><td class="py-2 px-3">{{ str_replace('_', ' ', $row['type']) }}</td><td class="py-2 px-3">{{ $itemName[$row['item_id']] ?? '' }}</td><td class="py-2 px-3">{{ $whName[$row['warehouse_id']] ?? '' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $q($row['quantity']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['unit_cost']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['value']) }}</td><td class="py-2 px-3">{{ $row['reference'] }}</td><td class="py-2 px-3 text-right">@if($row['journal_entry_id'])<a class="hover:underline" href="{{ route('accounting.journal-entries.show', $row['journal_entry_id']) }}">#{{ $row['journal_entry_id'] }}</a>@else — @endif</td></tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-gray-500">No movements.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
