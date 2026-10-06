<x-accounting::app-layout title="Item">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item['sku'] }} · {{ $item['name'] }}</h2>
            <div class="flex gap-2">
                @can('inventory.move')<a href="{{ route('accounting.inventory.movements.create', ['item_id' => $item['id']]) }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Move stock</a>@endcan
                @can('inventory.manage')<a href="{{ route('accounting.inventory.items.edit', $item['id']) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Edit</a>@endcan
                @if(! $locked)@can('inventory.manage')<form method="POST" action="{{ route('accounting.inventory.items.destroy', $item['id']) }}" onsubmit="return confirm('Delete this item?')">@csrf @method('DELETE')<button class="px-3 py-2 text-red-700 text-xs font-semibold uppercase tracking-widest hover:underline">Delete</button></form>@endcan @endif
                <a href="{{ route('accounting.inventory.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Inventory</a>
            </div>
        </div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.'))
    @php($where = collect($warehouses)->pluck('name', 'id'))
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">On hand</p><p class="text-2xl font-semibold tabular-nums">{{ $q($item['on_hand_quantity']) }} {{ $item['unit'] }}</p></div>
            <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">Value</p><p class="text-2xl font-semibold tabular-nums">{{ $fmt($item['on_hand_value']) }}</p></div>
            <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">Reorder level</p><p class="text-2xl font-semibold tabular-nums">{{ $q($item['reorder_level']) }}</p></div>
        </div>
        <div class="bg-white shadow rounded-lg p-5 overflow-x-auto"><h3 class="font-semibold text-gray-800 mb-2">Stock card</h3>
            <table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Date</th><th>Type</th><th>Warehouse</th><th class="text-right">Quantity</th><th class="text-right">Unit cost</th><th class="text-right">Value</th><th class="text-right">Balance</th><th class="text-right">Entry</th></tr></thead><tbody>
                @forelse ($card as $row)
                    <tr class="border-t"><td class="py-1">{{ $row['date'] }}</td><td>{{ str_replace('_', ' ', $row['type']) }}</td><td>{{ $where[$row['warehouse_id']] ?? '' }}</td><td class="text-right tabular-nums">{{ $q($row['quantity']) }}</td><td class="text-right tabular-nums">{{ $fmt($row['unit_cost']) }}</td><td class="text-right tabular-nums">{{ $fmt($row['value']) }}</td><td class="text-right tabular-nums">{{ $q($row['balance_quantity']) }} / {{ $fmt($row['balance_value']) }}</td><td class="text-right">@if($row['journal_entry_id'])<a class="hover:underline" href="{{ route('accounting.journal-entries.show', $row['journal_entry_id']) }}">#{{ $row['journal_entry_id'] }}</a>@else — @endif</td></tr>
                @empty
                    <tr><td colspan="8" class="py-4 text-center text-gray-500">No movements yet.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div></div>
</x-accounting::app-layout>
