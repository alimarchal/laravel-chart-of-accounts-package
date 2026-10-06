<x-accounting::app-layout title="Fixed assets">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fixed assets</h2>
            <div class="flex gap-2">
                <a href="{{ route('accounting.fixed-assets.depreciation') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Depreciation</a>
                <a href="{{ route('accounting.fixed-assets.export', ['format' => 'xlsx', 'as_of' => $register['as_of']]) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Export</a>
                @can('fixed-assets.create')<a href="{{ route('accounting.fixed-assets.create') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">New asset</a>@endcan
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">All modules</a>
            </div>
        </div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="date" name="as_of" value="{{ $filters['as_of'] }}" class="border-gray-300 rounded-md shadow-sm text-sm">
            <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="">All</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="disposed" @selected($filters['status'] === 'disposed')>Disposed</option></select>
            <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Update</button>
        </form>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (['Cost' => 'cost', 'Accumulated depreciation' => 'accumulated', 'Book value' => 'book_value'] as $label => $key)
                <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($register['totals'][$key]) }}</p></div>
            @endforeach
        </div>
        <p class="text-sm text-gray-600">Ledger vs register difference <span class="{{ (float) $reconcile['difference'] !== 0.0 ? 'font-medium text-red-700' : '' }}">{{ $fmt($reconcile['difference']) }}</span> (ledger {{ $fmt($reconcile['ledger']) }}, register {{ $fmt($reconcile['register']) }})</p>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Name</th><th class="py-2 px-3 text-left font-medium text-gray-600">Category</th><th class="py-2 px-3 text-left font-medium text-gray-600">Acquired</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th class="py-2 px-3 text-right font-medium text-gray-600">Cost</th><th class="py-2 px-3 text-right font-medium text-gray-600">Accumulated</th><th class="py-2 px-3 text-right font-medium text-gray-600">Book value</th></tr></thead>
            <tbody>
            @forelse ($register['rows'] as $row)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.fixed-assets.show', $row['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $row['code'] }}</a></td><td class="py-2 px-3">{{ $row['name'] }}</td><td class="py-2 px-3">{{ $row['category'] }}</td><td class="py-2 px-3">{{ $row['acquisition_date'] }}</td><td class="py-2 px-3">{{ $row['status'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['cost']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['accumulated']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['book_value']) }}</td></tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-gray-500">No assets yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
