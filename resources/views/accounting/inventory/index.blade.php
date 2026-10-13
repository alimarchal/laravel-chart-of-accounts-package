<x-accounting::app-layout title="Inventory">
    <x-slot name="header"><x-accounting::page-header title="Inventory" :showSearch="false" :showRefresh="false" :showPrint="false" backRoute="accounting.dashboard"><x-slot name="actions">
                <x-accounting::more-menu>
                    <a href="{{ route('accounting.inventory.movements.index') }}">Stock movements</a>
                    <a href="{{ route('accounting.inventory.warehouses.index') }}">Warehouses</a>
                    <hr>
                    <a href="{{ route('accounting.inventory.export', ['format' => 'xlsx', 'as_of' => $valuation['as_of']]) }}">Export valuation</a>
                </x-accounting::more-menu>
                @can('inventory.move')<a href="{{ route('accounting.inventory.movements.create') }}" class="acc-btn">Move stock</a>@endcan
                @can('inventory.manage')<a href="{{ route('accounting.inventory.items.create') }}" class="acc-btn acc-btn--primary">New item</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.'))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <x-accounting::list-filters :action="route('accounting.inventory.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'SKU, name or category'],
            ['name' => 'as_of', 'label' => 'As of', 'type' => 'date', 'quiet' => true],
            ['name' => 'warehouse_id', 'label' => 'Warehouse', 'type' => 'select', 'options' => collect($warehouses)->mapWithKeys(fn ($row) => [$row['id'] => $row['name']])->all()],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => collect($categories)->mapWithKeys(fn ($name) => [$name => $name])->all()],
            ['name' => 'low_stock', 'label' => 'Stock level', 'type' => 'select', 'options' => ['1' => 'At or below reorder level']],
        ]" />
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
