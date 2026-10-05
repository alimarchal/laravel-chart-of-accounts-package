<x-accounting::app-layout title="Financial Statements">
    <x-slot name="header">
        <x-accounting::page-header title="Financial Statements" :showSearch="false" backRoute="accounting.dashboard" />
    </x-slot>

    @php
        $money = function ($value) { if ($value === null) { return ''; } $amount = (float) $value; $text = number_format(abs($amount), 2); return $amount < 0 ? "({$text})" : $text; };
        $keys = match ($type) { 'balance-sheet' => ['as_of_date' => 'As of', 'compare_as_of' => 'Compare with (as of)'], 'income-statement' => ['date_from' => 'From', 'date_to' => 'To', 'compare_from' => 'Compare from', 'compare_to' => 'Compare to'], default => ['date_from' => 'From', 'date_to' => 'To'] };
        $hasCompare = ! empty($statement['compare_as_of']) || ! empty($statement['compare_to']);
        $query = array_filter(array_intersect_key($filters, $keys));
    @endphp

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['balance-sheet' => 'Balance sheet', 'income-statement' => 'Income statement', 'cash-flow' => 'Cash flow (indirect)'] as $value => $label)
                <a href="{{ route('accounting.reports.financial-statements', ['type' => $value]) }}" @class(['px-3 py-1.5 rounded-md text-sm font-semibold border', 'bg-indigo-700 text-white border-indigo-700' => $type === $value, 'bg-white text-gray-700 border-gray-300' => $type !== $value])>{{ $label }}</a>
            @endforeach
            <div class="ml-auto"><x-accounting::export-buttons :report="'statement-'.$type" /></div>
        </div>

        <form method="GET" action="{{ route('accounting.reports.financial-statements') }}" class="flex flex-wrap items-end gap-3 bg-white shadow rounded-lg p-4">
            <input type="hidden" name="type" value="{{ $type }}">
            @foreach ($keys as $key => $label)
                <div><x-accounting::label :for="$key" :value="$label" /><x-accounting::input :id="$key" :name="$key" type="date" class="mt-1" :value="$filters[$key] ?? ''" /></div>
            @endforeach
            <button type="submit" class="px-4 py-2 bg-indigo-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600">Show</button>
        </form>

        @if (($statement['unmapped_accounts'] ?? 0) > 0)
            <div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">{{ $statement['unmapped_accounts'] }} accounts are not mapped to a line and are shown on “unmapped” lines. @can('report-mapping.manage')<a href="{{ route('accounting.report-mapping.index') }}" class="underline">Map them</a>@endcan</div>
        @endif

        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                @if ($type === 'cash-flow')
                    @php
                        $flowLines = function (array $items) use ($money) { $html = ''; foreach ($items as $item) { $html .= '<tr class="border-t"><td class="py-1.5 px-3 pl-8"><details><summary class="cursor-pointer">'.e($item['label']).'</summary><ul class="pl-4 text-gray-500">'; foreach ($item['accounts'] as $account) { $html .= '<li>'.e($account['account_code'].' '.$account['account_name']).': '.e($money($account['amount'])).'</li>'; } $html .= '</ul></details></td><td class="py-1.5 px-3 text-right tabular-nums">'.e($money($item['amount'])).'</td></tr>'; } return $html; };
                    @endphp
                    @if ($statement['difference'] !== '0.00')<caption class="p-3 text-left text-red-700">The cash flow does not reconcile: difference {{ $money($statement['difference']) }}</caption>@endif
                    <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left">{{ $statement['from'] }} to {{ $statement['to'] }}</th><th class="py-2 px-3 text-right w-40">Amount</th></tr></thead>
                    <tbody>
                        <tr class="bg-gray-50 border-t"><td colspan="2" class="py-2 px-3 font-semibold">Operating activities</td></tr>
                        <tr class="border-t"><td class="py-1.5 px-3 pl-6">Profit for the period</td><td class="py-1.5 px-3 text-right tabular-nums">{{ $money($statement['profit']) }}</td></tr>
                        @if ($statement['operating']['non_cash'])<tr class="border-t"><td colspan="2" class="py-1.5 px-3 pl-6 text-gray-500">Adjustments for non-cash items</td></tr>{!! $flowLines($statement['operating']['non_cash']) !!}@endif
                        @if ($statement['operating']['working_capital'])<tr class="border-t"><td colspan="2" class="py-1.5 px-3 pl-6 text-gray-500">Changes in working capital</td></tr>{!! $flowLines($statement['operating']['working_capital']) !!}@endif
                        @if ($statement['operating']['unclassified'])<tr class="border-t"><td colspan="2" class="py-1.5 px-3 pl-6 text-amber-700">Unclassified (map these accounts)</td></tr>{!! $flowLines($statement['operating']['unclassified']) !!}@endif
                        <tr class="border-t-2 font-semibold"><td class="py-1.5 px-3">Net cash from operating activities</td><td class="py-1.5 px-3 text-right tabular-nums">{{ $money($statement['operating']['total']) }}</td></tr>
                        @foreach (['investing' => 'Investing activities', 'financing' => 'Financing activities'] as $key => $label)
                            <tr class="bg-gray-50 border-t"><td colspan="2" class="py-2 px-3 font-semibold">{{ $label }}</td></tr>
                            {!! $flowLines($statement[$key]['lines']) !!}
                            <tr class="border-t-2 font-semibold"><td class="py-1.5 px-3">Net cash from {{ strtolower($label) }}</td><td class="py-1.5 px-3 text-right tabular-nums">{{ $money($statement[$key]['total']) }}</td></tr>
                        @endforeach
                        <tr class="border-t-2 font-semibold"><td class="py-1.5 px-3">Net change in cash</td><td class="py-1.5 px-3 text-right tabular-nums">{{ $money($statement['net_change']) }}</td></tr>
                        <tr class="border-t"><td class="py-1.5 px-3">Cash at the beginning of the period</td><td class="py-1.5 px-3 text-right tabular-nums">{{ $money($statement['opening_cash']) }}</td></tr>
                        <tr class="border-t-2 font-semibold"><td class="py-1.5 px-3">Cash at the end of the period</td><td class="py-1.5 px-3 text-right tabular-nums">{{ $money($statement['closing_cash']) }}</td></tr>
                    </tbody>
                @else
                    <thead class="bg-gray-50"><tr>
                        <th class="py-2 px-3 text-left">{{ $type === 'balance-sheet' ? 'As of '.$statement['as_of'] : $statement['from'].' to '.$statement['to'] }}</th>
                        <th class="py-2 px-3 text-right w-40">Current</th>
                        @if ($hasCompare)<th class="py-2 px-3 text-right w-40">{{ $type === 'balance-sheet' ? $statement['compare_as_of'] : $statement['compare_from'].' – '.$statement['compare_to'] }}</th>@endif
                    </tr></thead>
                    <tbody>
                        @foreach ($statement['sections'] as $section)
                            <tr class="bg-gray-50 border-t"><td colspan="{{ $hasCompare ? 3 : 2 }}" class="py-2 px-3 font-semibold">{{ $section['name'] }}</td></tr>
                            @foreach ($section['lines'] as $line)
                                <tr class="border-t">
                                    <td class="py-1.5 px-3 pl-6"><details><summary class="cursor-pointer">{{ $line['name'] }}</summary><ul class="pl-4 text-gray-500">@foreach ($line['accounts'] as $account)<li>{{ $account['account_code'] }} {{ $account['account_name'] }}: {{ $money($account['amount']['current']) }}</li>@endforeach</ul></details></td>
                                    <td class="py-1.5 px-3 text-right tabular-nums">{{ $money($line['amount']['current']) }}</td>
                                    @if ($hasCompare)<td class="py-1.5 px-3 text-right tabular-nums">{{ $money($line['amount']['compare']) }}</td>@endif
                                </tr>
                            @endforeach
                            <tr class="border-t font-medium"><td class="py-1.5 px-3 pl-6">Total {{ strtolower($section['name']) }}</td><td class="py-1.5 px-3 text-right tabular-nums">{{ $money($section['total']['current']) }}</td>@if ($hasCompare)<td class="py-1.5 px-3 text-right tabular-nums">{{ $money($section['total']['compare']) }}</td>@endif</tr>
                        @endforeach
                        @foreach ($statement['subtotals'] ?? $statement['totals'] as $name => $value)
                            <tr class="border-t-2 font-semibold"><td class="py-2 px-3">{{ ucfirst(str_replace('_', ' ', $name)) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($value['current']) }}</td>@if ($hasCompare)<td class="py-2 px-3 text-right tabular-nums">{{ $money($value['compare']) }}</td>@endif</tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>
    </div></div>
</x-accounting::app-layout>
