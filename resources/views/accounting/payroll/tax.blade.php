<x-accounting::app-layout title="Salary tax">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Salary tax statement · {{ $label }}</h2><div class="flex gap-2"><a href="{{ route('accounting.payroll.reports.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Reports</a><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="flex flex-wrap items-end gap-6 text-sm">
            <form method="GET" class="flex items-end gap-2"><label><span class="text-gray-700">Tax year starting in</span><input type="number" name="year" value="{{ $tax_year }}" class="mt-1 block w-28 border-gray-300 rounded-md shadow-sm text-sm"></label><button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Show</button></form>
            <form method="GET" class="flex items-end gap-2" onsubmit="this.action = '{{ url(route('accounting.payroll.tax.certificate', 0, false)) }}'.replace(/0$/, this.employee.value); return !!this.employee.value">
                <label><span class="text-gray-700">Tax certificate of</span><select name="employee" class="mt-1 block w-64 border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose an employee…</option>@foreach ($employees as $row)<option value="{{ $row['id'] }}">{{ $row['code'] }} {{ $row['name'] }}</option>@endforeach</select></label>
                <input type="hidden" name="year" value="{{ $tax_year }}"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Open certificate</button></form>
        </div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><div class="flex items-center justify-between p-4"><h3 class="font-semibold text-gray-800">Everybody, {{ $label }}</h3><div class="flex gap-1">@foreach (['csv', 'xlsx', 'pdf'] as $format)<a class="px-2 py-1 border border-gray-300 rounded text-xs hover:bg-gray-50" href="{{ route('accounting.payroll.tax.export', [$format, 'year' => $tax_year]) }}">{{ strtoupper($format) }}</a>@endforeach</div></div>
            <table class="min-w-full text-sm"><thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Employee</th><th class="py-2 px-3 text-left font-medium text-gray-600">National ID</th><th class="py-2 px-3 text-right font-medium text-gray-600">Months</th><th class="py-2 px-3 text-right font-medium text-gray-600">Taxable income</th><th class="py-2 px-3 text-right font-medium text-gray-600">Tax withheld</th><th class="py-2 px-3 text-right font-medium text-gray-600">Net pay</th></tr></thead><tbody>
            @forelse ($rows as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['Employee code'] }} {{ $row['Employee name'] }}</td><td class="py-2 px-3">{{ $row['National ID'] }}</td><td class="py-2 px-3 text-right">{{ $row['Months paid'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['Taxable income']) }}</td><td class="py-2 px-3 text-right font-medium tabular-nums">{{ $money($row['Tax withheld']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['Net pay']) }}</td></tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-500">No salary was paid in this tax year.</td></tr>
            @endforelse
            </tbody></table></div>
    </div></div>
</x-accounting::app-layout>
