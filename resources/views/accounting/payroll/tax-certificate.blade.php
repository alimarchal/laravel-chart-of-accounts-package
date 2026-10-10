<x-accounting::app-layout title="Tax certificate">
    <x-slot name="header"><div class="flex items-center justify-between print:hidden"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Salary tax certificate · {{ $certificate['employee']['code'] }} · {{ $certificate['label'] }}</h2><div class="flex gap-2"><button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Print</button><a href="{{ route('accounting.payroll.tax.index', ['year' => $certificate['tax_year']]) }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Back</a></div></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    @php($employee = $certificate['employee'])
    <div class="py-6"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8"><div class="bg-white shadow rounded-lg p-6 space-y-4 text-sm">
        <h2 class="hidden print:block text-lg font-semibold">Salary tax certificate — tax year {{ $certificate['label'] }}</h2>
        <div class="grid gap-2 sm:grid-cols-2">
            <div><span class="text-gray-500">Employee</span><p class="font-medium">{{ $employee['name'] }} ({{ $employee['code'] }})</p></div>
            <div><span class="text-gray-500">National ID</span><p>{{ $employee['national_id'] ?? '—' }}</p></div>
            <div><span class="text-gray-500">Designation</span><p>{{ $employee['designation'] ?? '—' }}</p></div>
            <div><span class="text-gray-500">Joined</span><p>{{ $employee['join_date'] }}</p></div>
        </div>
        <table class="w-full"><thead><tr class="text-left text-gray-500"><th class="py-1">Month</th><th class="text-right">Gross</th><th class="text-right">Taxable income</th><th class="text-right">Tax withheld</th><th class="text-right">Net pay</th></tr></thead><tbody>
            @forelse ($certificate['months'] as $row)
                <tr class="border-t"><td class="py-1">{{ $row['month'] }}</td><td class="text-right tabular-nums">{{ $money($row['gross']) }}</td><td class="text-right tabular-nums">{{ $money($row['taxable']) }}</td><td class="text-right tabular-nums">{{ $money($row['tax']) }}</td><td class="text-right tabular-nums">{{ $money($row['net']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="py-4 text-center text-gray-500">No salary was paid in this tax year.</td></tr>
            @endforelse
        </tbody><tfoot><tr class="border-t font-semibold"><td class="py-1">Total</td><td class="text-right tabular-nums">{{ $money($certificate['totals']['gross']) }}</td><td class="text-right tabular-nums">{{ $money($certificate['totals']['taxable']) }}</td><td class="text-right tabular-nums">{{ $money($certificate['totals']['tax']) }}</td><td class="text-right tabular-nums">{{ $money($certificate['totals']['net']) }}</td></tr></tfoot></table>
        <p class="text-xs text-gray-500">Worked out from the salaries posted in the books for the tax year {{ $certificate['label'] }}.</p>
    </div></div></div>
</x-accounting::app-layout>
