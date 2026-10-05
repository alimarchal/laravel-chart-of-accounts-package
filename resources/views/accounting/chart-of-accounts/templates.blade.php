<x-accounting::app-layout title="Chart Templates">
    <x-slot name="header">
        <x-accounting::page-header title="Chart Templates" :showSearch="false" backRoute="accounting.chart-of-accounts.index" />
    </x-slot>

    @php($styles = ['new' => 'bg-emerald-100 text-emerald-800', 'exists' => 'bg-gray-100 text-gray-600', 'different' => 'bg-amber-100 text-amber-800', 'blocked' => 'bg-red-100 text-red-700'])
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <p class="text-sm text-gray-600">Start from an industry chart, or add an industry's accounts to the chart you have. Accounts that exist are never changed.</p>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <div @class(['bg-white shadow rounded-lg p-4 border', 'border-indigo-500' => $selected === $template['key'], 'border-transparent' => $selected !== $template['key']])>
                    <h3 class="font-semibold text-gray-800">{{ $template['name'] }}</h3>
                    <p class="text-sm text-gray-600 mt-1">{{ $template['description'] }}</p>
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-gray-500">{{ $template['accounts'] }} accounts{{ $template['extras'] > 0 ? ', '.$template['extras'].' industry-specific' : '' }}</span>
                        <a href="{{ route('accounting.chart-templates.index', ['template' => $template['key']]) }}" class="px-3 py-1 rounded-md border border-gray-300 text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Preview</a>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($preview)
            <div class="bg-white shadow rounded-lg p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-800">{{ $preview['template']['name'] }}: what would be added</h3>
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <span class="rounded-full px-2 py-0.5 {{ $styles['new'] }}">{{ $preview['summary']['new'] }} new</span>
                        <span class="rounded-full px-2 py-0.5 {{ $styles['exists'] }}">{{ $preview['summary']['exists'] }} already there</span>
                        @if ($preview['summary']['different'])<span class="rounded-full px-2 py-0.5 {{ $styles['different'] }}">{{ $preview['summary']['different'] }} named differently</span>@endif
                        @if ($preview['summary']['blocked'])<span class="rounded-full px-2 py-0.5 {{ $styles['blocked'] }}">{{ $preview['summary']['blocked'] }} blocked</span>@endif
                    </div>
                </div>
                <div class="max-h-[28rem] overflow-y-auto border rounded-md">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0"><tr>
                            <th class="py-2 px-3 text-left font-medium text-gray-600">Code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-600">Parent</th><th class="py-2 px-3 text-left font-medium text-gray-600">Type</th>
                            <th class="py-2 px-3 text-left font-medium text-gray-600">Statement line</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th>
                        </tr></thead>
                        <tbody>
                            @foreach (collect($preview['rows'])->filter(fn ($row) => $row['status'] !== 'exists') as $row)
                                <tr class="border-t">
                                    <td class="py-1.5 px-3 font-mono">{{ $row['account_code'] }}</td>
                                    <td class="py-1.5 px-3 {{ $row['is_group'] ? 'font-semibold' : '' }}">{{ $row['account_name'] }}@if ($row['existing_name']) <span class="text-xs text-gray-500">(yours: {{ $row['existing_name'] }})</span>@endif</td>
                                    <td class="py-1.5 px-3 font-mono text-gray-500">{{ $row['parent_code'] ?? '—' }}</td>
                                    <td class="py-1.5 px-3">{{ $row['type'] }}</td>
                                    <td class="py-1.5 px-3 text-gray-500">{{ $row['line'] ?? '—' }}</td>
                                    <td class="py-1.5 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$row['status']] }}">{{ $row['status'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($preview['summary']['new'] > 0)
                    <form method="POST" action="{{ route('accounting.chart-templates.apply', $preview['template']['key']) }}" class="flex justify-end">@csrf
                        <button type="submit" class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Add {{ $preview['summary']['new'] }} accounts</button>
                    </form>
                @else
                    <p class="text-sm text-gray-600">Nothing to add: the chart already has every account of this template.</p>
                @endif
            </div>
        @endif
    </div></div>
</x-accounting::app-layout>
