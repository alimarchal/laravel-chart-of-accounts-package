<x-accounting::app-layout title="Payroll reports">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll reports</h2><div class="flex gap-2">@accountingFeature('payroll_reports')<a href="{{ route('accounting.payroll.tax.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Tax</a>@endaccountingFeature<a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    @php($links = fn ($report) => collect(['csv', 'xlsx', 'pdf'])->map(fn ($format) => '<a class="px-2 py-1 border border-gray-300 rounded text-xs hover:bg-gray-50" href="'.e(route('accounting.payroll.reports.export', [$report, $format, 'year' => $year, 'from' => $from, 'to' => $to])).'">'.strtoupper($format).'</a>')->implode(' '))
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label><span class="text-gray-700">Year</span><input type="number" name="year" value="{{ $year }}" class="mt-1 block w-28 border-gray-300 rounded-md shadow-sm text-sm"></label>
            <label><span class="text-gray-700">Cost centers from</span><input type="month" name="from" value="{{ $from }}" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"></label>
            <label><span class="text-gray-700">to</span><input type="month" name="to" value="{{ $to }}" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"></label>
            <button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Show</button>
        </form>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><div class="flex items-center justify-between p-4"><h3 class="font-semibold text-gray-800">Month by month {{ $year }}</h3><div class="flex gap-1">{!! $links('comparison') !!}</div></div>
            <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr>@foreach (['Month' => 'left', 'Employees' => 'right', 'Gross' => 'right', 'Tax' => 'right', 'Net' => 'right', 'Employer' => 'right', 'Total cost' => 'right', 'Per head' => 'right', 'Change' => 'right'] as $h => $align)<th class="py-2 px-3 text-{{ $align }} font-medium text-gray-600">{{ $h }}</th>@endforeach</tr></thead><tbody>
            @forelse ($comparison as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['month'] }}</td><td class="py-2 px-3 text-right">{{ $row['employees'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['gross']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['tax']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['net']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['employer']) }}</td><td class="py-2 px-3 text-right font-medium tabular-nums">{{ $money($row['total_cost']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['cost_per_employee']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $row['change'] === null ? '—' : $money($row['change']).' ('.($row['change_percent'] ?? 0).'%)' }}</td></tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-gray-500">No posted payroll in {{ $year }}.</td></tr>
            @endforelse
            </tbody></table></div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><div class="flex items-center justify-between p-4"><h3 class="font-semibold text-gray-800">Cost centers {{ $from }} – {{ $to }}</h3><div class="flex gap-1">{!! $links('cost-centers') !!}</div></div>
            <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Cost center</th><th class="py-2 px-3 text-right font-medium text-gray-600">Employees</th><th class="py-2 px-3 text-right font-medium text-gray-600">Pay</th><th class="py-2 px-3 text-right font-medium text-gray-600">Employer contributions</th><th class="py-2 px-3 text-right font-medium text-gray-600">Total cost</th></tr></thead><tbody>
            @forelse ($cost_centers as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['cost_center'] }}</td><td class="py-2 px-3 text-right">{{ $row['employees'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['pay']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['employer']) }}</td><td class="py-2 px-3 text-right font-medium tabular-nums">{{ $money($row['total_cost']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-gray-500">Nothing posted in those months.</td></tr>
            @endforelse
            </tbody></table></div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><div class="flex items-center justify-between p-4"><h3 class="font-semibold text-gray-800">Headcount {{ $year }}</h3><div class="flex gap-1">{!! $links('headcount') !!}</div></div>
            <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Month</th><th class="py-2 px-3 text-right font-medium text-gray-600">Joined</th><th class="py-2 px-3 text-right font-medium text-gray-600">Left</th><th class="py-2 px-3 text-right font-medium text-gray-600">On the payroll</th></tr></thead><tbody>
            @foreach ($headcount as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['month'] }}</td><td class="py-2 px-3 text-right">{{ $row['joined'] }}</td><td class="py-2 px-3 text-right">{{ $row['left'] }}</td><td class="py-2 px-3 text-right font-medium">{{ $row['on_payroll'] }}</td></tr>
            @endforeach
            </tbody></table></div>
    </div></div>
</x-accounting::app-layout>
