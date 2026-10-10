<x-accounting::app-layout title="Features">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Features</h2><a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Accounting</a></div></x-slot>
    <div class="py-6"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Turn a module or a payroll feature off to hide its screens and close its API; its data is kept and comes back when you turn it on again. The core (chart of accounts, journal entries, periods, reports, users) is always on.</p>
        <form method="POST" action="{{ route('settings.features.update') }}" class="space-y-6">
            @csrf @method('PUT')
            @foreach (collect($features)->groupBy('group') as $group => $rows)
                <div class="bg-white shadow rounded-lg divide-y">
                    <h3 class="px-5 py-3 font-semibold text-gray-800">{{ $group }}</h3>
                    @foreach ($rows as $row)
                        <label class="flex items-start gap-3 px-5 py-3 text-sm {{ $row['parent'] ? 'pl-10' : '' }}">
                            <input type="hidden" name="features[{{ $row['key'] }}]" value="0">
                            <input type="checkbox" name="features[{{ $row['key'] }}]" value="1" class="mt-1 rounded border-gray-300" @checked($row['own'])>
                            <span><span class="font-medium text-gray-900">{{ $row['label'] }}</span>
                                @if($row['own'] && ! $row['enabled'])<span class="ml-2 text-xs text-amber-700">off because {{ $row['parent'] }} is off</span>@endif
                                @if($row['source'] === 'config' && ! $row['default'])<span class="ml-2 text-xs text-gray-500">off by default (config)</span>@endif
                                <span class="block text-gray-500">{{ $row['description'] }}</span></span>
                        </label>
                    @endforeach
                </div>
            @endforeach
            <button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Save</button>
        </form>
    </div></div>
</x-accounting::app-layout>
