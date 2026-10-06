@php
    $fmt = fn ($value) => number_format((float) $value, 2);
    $perf = $overview['performance'];
    $labels = ['not_due' => 'Not due', 'days_1_30' => '1–30', 'days_31_60' => '31–60', 'days_61_90' => '61–90', 'over_90' => '90+'];
    $links = [
        'pending_approval' => route('accounting.journal-entries.index', ['filter' => ['approval_status' => 'pending']]),
        'drafts' => route('accounting.journal-entries.index', ['filter' => ['status' => 'draft']]),
        'overdue_receivables' => route('accounting.receivables.aging'),
        'unmatched_bank_lines' => route('accounting.bank-statements.index'),
        'budget_over' => route('accounting.budgets.index'),
        'budget_warning' => route('accounting.budgets.index'),
        'tax_unfiled' => route('accounting.tax.index'),
    ];
    $icons = ['critical' => '▲', 'warning' => '●', 'info' => '○'];
    $trend = $perf['trend'] ?? [];
    $max = max(1, ...array_map(fn ($row) => max((float) $row['income'], (float) $row['expense']), $trend ?: [['income' => 0, 'expense' => 0]]));
    $width = 640; $height = 220; $left = 48; $bottom = 24; $top = 8;
    $step = $trend ? ($width - $left) / count($trend) : 1;
    $barWidth = min(24, ($step - 12) / 2);
    $y = fn ($value) => $top + ($height - $top - $bottom) * (1 - max(0, $value) / $max);
@endphp
<x-accounting::app-layout title="Overview">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Overview <span class="text-sm font-normal text-gray-500">as of {{ $overview['as_of'] }}</span></h2>
            <div class="flex gap-2 items-center">
                <form method="GET" class="flex gap-2 items-center"><input type="date" name="as_of" value="{{ $overview['as_of'] }}" class="border-gray-300 rounded-md shadow-sm text-sm"><button class="px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Update</button></form>
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">All modules</a>
            </div>
        </div>
    </x-slot>
    <style>
        .viz-root{--viz-1:#2a78d6;--viz-2:#eb6834;--viz-grid:rgba(0,0,0,.08);--viz-axis:rgba(0,0,0,.45)}
        @media (prefers-color-scheme:dark){:root:not([data-theme="light"]) .viz-root{--viz-1:#3987e5;--viz-2:#d95926;--viz-grid:rgba(255,255,255,.1);--viz-axis:rgba(255,255,255,.55)}}
        :root[data-theme="dark"] .viz-root{--viz-1:#3987e5;--viz-2:#d95926;--viz-grid:rgba(255,255,255,.1);--viz-axis:rgba(255,255,255,.55)}
    </style>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if (count($overview['alerts']))
            <div class="bg-white shadow rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-3">Needs attention</h3>
                <div class="space-y-2">
                    @foreach ($overview['alerts'] as $alert)
                        <a href="{{ $links[$alert['key']] ?? route('accounting.dashboard') }}" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-gray-50">
                            <span><span aria-hidden="true" class="mr-2">{{ $icons[$alert['level']] ?? '' }}</span><span class="sr-only">{{ $alert['level'] }}: </span>{{ $alert['label'] }}</span>
                            <span class="tabular-nums">{{ $alert['amount'] !== null ? $fmt($alert['amount']) : $alert['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @if ($overview['cash'])
                <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">Cash and bank</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($overview['cash']['total']) }}</p><p class="mt-1 text-xs text-gray-500">{{ count($overview['cash']['accounts']) }} bank account(s)</p></div>
            @endif
            @foreach (['receivables' => 'Receivable', 'payables' => 'Payable'] as $key => $label)
                @if ($overview[$key])
                    <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($overview[$key]['total']) }}</p><p class="mt-1 text-xs text-gray-500">{{ $fmt($overview[$key]['overdue']) }} overdue</p></div>
                @endif
            @endforeach
            @if ($perf)
                <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">Net this month</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($perf['this_month']['net']) }}</p><p class="mt-1 text-xs text-gray-500">Last month {{ $fmt($perf['last_month']['net']) }}</p></div>
            @endif
        </div>

        @if ($perf)
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="bg-white shadow rounded-lg p-5 lg:col-span-2 viz-root" x-data="{ table: false, hover: null, rows: @js($trend) }">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-800">Income and expense</h3>
                            <p class="text-xs text-gray-500">Last {{ $overview['months'] }} months, closing entries excluded</p>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:var(--viz-1)"></span>Income</span>
                            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:var(--viz-2)"></span>Expense</span>
                            <button type="button" @click="table = !table" class="text-xs text-indigo-700 hover:underline" x-text="table ? 'Show chart' : 'Show table'"></button>
                        </div>
                    </div>
                    <div x-show="table" x-cloak>
                        <table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Month</th><th class="py-1 text-right">Income</th><th class="py-1 text-right">Expense</th></tr></thead><tbody>
                            @foreach ($trend as $row)<tr class="border-t"><td class="py-1">{{ $row['month'] }}</td><td class="py-1 text-right tabular-nums">{{ $fmt($row['income']) }}</td><td class="py-1 text-right tabular-nums">{{ $fmt($row['expense']) }}</td></tr>@endforeach
                        </tbody></table>
                    </div>
                    <div x-show="!table" class="relative">
                        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="Income and expense by month" class="w-full">
                            @foreach ([0, 0.5, 1] as $fraction)
                                @php($tick = $max * $fraction)
                                <line x1="{{ $left }}" x2="{{ $width }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="var(--viz-grid)" stroke-width="1"/>
                                <text x="{{ $left - 6 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" font-size="11" fill="var(--viz-axis)">{{ $tick >= 1000 ? round($tick / 1000, 1).'K' : round($tick) }}</text>
                            @endforeach
                            @foreach ($trend as $i => $row)
                                @php($x = $left + $i * $step + ($step - ($barWidth * 2 + 2)) / 2)
                                <g @mouseenter="hover = {{ $i }}" @mouseleave="hover = null">
                                    <rect x="{{ $left + $i * $step }}" y="{{ $top }}" width="{{ $step }}" height="{{ $height - $top - $bottom }}" fill="transparent"/>
                                    <rect x="{{ $x }}" y="{{ $y((float) $row['income']) }}" width="{{ $barWidth }}" height="{{ max(0, $height - $bottom - $y((float) $row['income'])) }}" rx="4" fill="var(--viz-1)"/>
                                    <rect x="{{ $x + $barWidth + 2 }}" y="{{ $y((float) $row['expense']) }}" width="{{ $barWidth }}" height="{{ max(0, $height - $bottom - $y((float) $row['expense'])) }}" rx="4" fill="var(--viz-2)"/>
                                    <text x="{{ $left + $i * $step + $step / 2 }}" y="{{ $height - 6 }}" text-anchor="middle" font-size="11" fill="var(--viz-axis)">{{ substr($row['month'], 2) }}</text>
                                </g>
                            @endforeach
                        </svg>
                        <div x-show="hover !== null" x-cloak class="pointer-events-none absolute right-2 top-2 rounded-md border bg-white px-3 py-2 text-xs shadow">
                            <p class="font-medium" x-text="hover !== null ? rows[hover].month : ''"></p>
                            <p>Income <span class="tabular-nums" x-text="hover !== null ? Number(rows[hover].income).toLocaleString(undefined,{minimumFractionDigits:2}) : ''"></span></p>
                            <p>Expense <span class="tabular-nums" x-text="hover !== null ? Number(rows[hover].expense).toLocaleString(undefined,{minimumFractionDigits:2}) : ''"></span></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white shadow rounded-lg p-5 text-sm space-y-2">
                    <h3 class="font-semibold text-gray-800">Year to date</h3>
                    <div class="flex justify-between"><span>Income</span><span class="tabular-nums">{{ $fmt($perf['year_to_date']['income']) }}</span></div>
                    <div class="flex justify-between"><span>Expense</span><span class="tabular-nums">{{ $fmt($perf['year_to_date']['expense']) }}</span></div>
                    <div class="flex justify-between border-t pt-2 font-medium"><span>Net</span><span class="tabular-nums">{{ $fmt($perf['year_to_date']['net']) }}</span></div>
                    @if (count($perf['top_expenses']))<p class="pt-3 text-xs font-medium text-gray-500">Top expenses this month</p>@endif
                    @foreach ($perf['top_expenses'] as $row)<div class="flex justify-between"><span class="truncate pr-2">{{ $row['label'] }}</span><span class="tabular-nums">{{ $fmt($row['amount']) }}</span></div>@endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach (['receivables' => 'Receivables ageing', 'payables' => 'Payables ageing'] as $key => $title)
                @if ($overview[$key])
                    @php($side = $overview[$key])
                    @php($sideMax = max(1, ...array_map(fn ($b) => (float) $b['amount'], $side['buckets'])))
                    <div class="bg-white shadow rounded-lg p-5 viz-root">
                        <h3 class="font-semibold text-gray-800">{{ $title }}</h3>
                        <p class="text-xs text-gray-500 mb-3">Ledger vs sub-ledger difference {{ $fmt($side['difference']) }}</p>
                        <div class="space-y-2">
                            @foreach ($side['buckets'] as $bucket)
                                <div class="flex items-center gap-3 text-sm"><span class="w-14 text-gray-500">{{ $labels[$bucket['bucket']] }}</span><div class="flex-1 h-4"><div class="h-4 rounded-r" style="width: {{ max(0, (float) $bucket['amount']) / $sideMax * 100 }}%; background: var(--viz-1)" title="{{ $fmt($bucket['amount']) }}"></div></div><span class="w-28 text-right tabular-nums">{{ $fmt($bucket['amount']) }}</span></div>
                            @endforeach
                        </div>
                        <div class="mt-4 space-y-1 text-sm">
                            @foreach ($side['top'] as $row)<div class="flex justify-between"><a href="{{ route('accounting.parties.show', $row['party_id']) }}" class="hover:underline">{{ $row['name'] }}</a><span class="tabular-nums">{{ $fmt($row['total']) }}</span></div>@endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($overview['cash'] && count($overview['cash']['accounts']))
            <div class="bg-white shadow rounded-lg p-5 text-sm space-y-1"><h3 class="font-semibold text-gray-800 mb-2">Bank balances</h3>
                @foreach ($overview['cash']['accounts'] as $account)<div class="flex justify-between"><span>{{ $account['name'] }}</span><span class="tabular-nums">{{ $fmt($account['balance']) }}</span></div>@endforeach
            </div>
        @endif

        @if ($overview['recent_entries'] !== null)
            <div class="bg-white shadow rounded-lg p-5"><h3 class="font-semibold text-gray-800 mb-2">Recent journal entries</h3>
                <table class="w-full text-sm"><tbody>
                    @foreach ($overview['recent_entries'] as $entry)
                        <tr class="border-t first:border-0"><td class="py-2"><a href="{{ route('accounting.journal-entries.show', $entry['id']) }}" class="hover:underline">{{ $entry['voucher_number'] ?? '#'.$entry['id'] }}</a></td><td class="py-2 text-gray-500">{{ $entry['entry_date'] }}</td><td class="py-2">{{ $entry['description'] }}</td><td class="py-2"><span class="rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-700">{{ $entry['status'] }}</span></td><td class="py-2 text-right tabular-nums">{{ $fmt($entry['amount']) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        @endif
    </div></div>
</x-accounting::app-layout>
