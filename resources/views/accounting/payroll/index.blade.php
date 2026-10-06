<x-accounting::app-layout title="Payroll">
    <x-slot name="header">
        <div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll</h2>
            <div class="flex gap-2"><a href="{{ route('accounting.payroll.employees.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Employees</a><a href="{{ route('accounting.payroll.components.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Allowances &amp; deductions</a><a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">All modules</a></div></div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (['Gross' => 'gross', 'Tax withheld' => 'tax', 'Net paid' => 'net'] as $label => $key)
                <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">{{ $label }} {{ $year }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt(collect($summary)->sum(fn ($row) => (float) $row[$key])) }}</p></div>
            @endforeach
        </div>
        @can('payroll.run')
            <form method="POST" action="{{ route('accounting.payroll.runs.store') }}" class="bg-white shadow rounded-lg p-5 flex flex-wrap items-end gap-3">
                @csrf <h3 class="font-semibold text-gray-800 w-full">New payroll run</h3>
                <label class="text-sm"><span class="text-gray-700">Month</span><input type="date" name="period_month" value="{{ old('period_month', $defaultMonth) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Notes</span><input name="notes" value="{{ old('notes') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Calculate</button>
            </form>
        @endcan
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Month</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th class="py-2 px-3 text-right font-medium text-gray-600">Employees</th><th class="py-2 px-3 text-right font-medium text-gray-600">Gross</th><th class="py-2 px-3 text-right font-medium text-gray-600">Deductions</th><th class="py-2 px-3 text-right font-medium text-gray-600">Tax</th><th class="py-2 px-3 text-right font-medium text-gray-600">Net</th></tr></thead>
            <tbody>
            @forelse ($runs as $run)
                <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.payroll.runs.show', $run['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $run['period_month'] }}</a></td><td class="py-2 px-3">{{ $run['status'] }}</td><td class="py-2 px-3 text-right">{{ $run['employees'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($run['gross']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($run['deductions']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($run['tax']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($run['net']) }}</td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No payroll runs yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
