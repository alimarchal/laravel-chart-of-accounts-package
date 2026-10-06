<x-accounting::app-layout title="Inventory">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Inventory</h2>
            <div class="flex gap-2">
                <a href="{{ route('accounting.inventory.movements.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Movements</a>
                <a href="{{ route('accounting.inventory.warehouses.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Warehouses</a>
                <a href="{{ route('accounting.inventory.export', ['format' => 'xlsx', 'as_of' => $valuation['as_of']]) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Export</a>
                @can('inventory.move')<a href="{{ route('accounting.inventory.movements.create') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Move stock</a>@endcan
                @can('inventory.manage')<a href="{{ route('accounting.inventory.items.create') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">New item</a>@endcan
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">All modules</a>
            </div>
        </div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.'))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="date" name="as_of" value="{{ $filters['as_of'] }}" class="border-gray-300 rounded-md shadow-sm text-sm">
            <select name="warehouse_id" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All warehouses</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse['id'] }}" @selected((string) $filters['warehouse_id'] === (string) $warehouse['id'])>{{ $warehouse['name'] }}</option>@endforeach</select>
            <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Update</button>
        </form>
        <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">Stock value</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($valuation['totals']['value']) }}</p>
            <p class="mt-1 text-xs text-gray-500">Ledger vs stock difference <span class="{{ (float) $valuation['reconcile']['difference'] !== 0.0 ? 'font-medium text-red-700' : '' }}">{{ $fmt($valuation['reconcile']['difference']) }}</span> (ledger {{ $fmt($valuation['reconcile']['ledger']) }}, stock {{ $fmt($valuation['reconcile']['stock']) }})</p></div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">SKU</th><th class="py-2 px-3 text-left font-medium text-gray-600">Item</th><th class="py-2 px-3 text-right font-medium text-gray-600">Quantity</th><th class="py-2 px-3 text-right font-medium text-gray-600">Average cost</th><th class="py-2 px-3 text-right font-medium text-gray-600">Value</th><th class="py-2 px-3 text-left font-medium text-gray-600">Where</th></tr></thead>
            <tbody>
            @forelse ($valuation['rows'] as $row)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.inventory.items.show', $row['item_id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $row['sku'] }}</a></td><td class="py-2 px-3">{{ $row['name'] }} @if($row['low'])<span class="ml-1 rounded-full px-2 py-0.5 text-xs bg-red-100 text-red-700">low</span>@endif</td><td class="py-2 px-3 text-right tabular-nums">{{ $q($row['quantity']) }} {{ $row['unit'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['average_cost']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['value']) }}</td><td class="py-2 px-3 text-gray-500">{{ collect($row['warehouses'])->map(fn ($w) => $w['name'].': '.$q($w['quantity']))->implode(', ') }}</td></tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-gray-500">No stock yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
