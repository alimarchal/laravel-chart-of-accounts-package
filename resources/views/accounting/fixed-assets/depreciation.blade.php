<x-accounting::app-layout title="Depreciation">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Depreciation</h2>
            <a href="{{ route('accounting.fixed-assets.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Register</a>
        </div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <p class="text-sm text-gray-600">What is due, month by month, up to the end of the chosen month.</p>
        <div class="flex flex-wrap items-end gap-3">
            <form method="GET" class="flex gap-2 items-end"><input type="date" name="up_to" value="{{ $upTo }}" class="border-gray-300 rounded-md shadow-sm text-sm"><button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Preview</button></form>
            @if(count($preview['months']))@can('fixed-assets.depreciate')<form method="POST" action="{{ route('accounting.fixed-assets.depreciation.run') }}" onsubmit="return confirm('Book {{ $fmt($preview['total']) }} of depreciation?')">@csrf<input type="hidden" name="up_to" value="{{ $preview['up_to'] }}"><button class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Book {{ $fmt($preview['total']) }}</button></form>@endcan @endif
        </div>
        @forelse ($preview['months'] as $month)
            <div class="bg-white shadow rounded-lg p-5"><h3 class="font-semibold text-gray-800">{{ $month['month'] }} <span class="text-sm font-normal text-gray-500">{{ $fmt($month['total']) }} across {{ count($month['assets']) }} asset(s)</span></h3>
                <table class="w-full text-sm mt-2"><tbody>@foreach ($month['assets'] as $asset)<tr class="border-t first:border-0"><td class="py-1">{{ $asset['code'] }}</td><td>{{ $asset['name'] }}</td><td class="text-right tabular-nums">{{ $fmt($asset['amount']) }}</td></tr>@endforeach</tbody></table>
            </div>
        @empty
            <div class="bg-white shadow rounded-lg p-8 text-center text-gray-500">Nothing to depreciate up to {{ $preview['up_to'] }}.</div>
        @endforelse
    </div></div>
</x-accounting::app-layout>
