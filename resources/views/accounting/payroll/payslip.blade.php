<x-accounting::app-layout title="Payslip">
    <x-slot name="header">
        <div class="flex items-center justify-between print:hidden"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Payslip {{ $run['period_month'] }} · {{ $employee['name'] }}</h2>
            <div class="flex gap-2"><button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Print</button><a href="{{ route('accounting.payroll.runs.show', $run['id']) }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Back</a></div></div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($earnings = collect($payslip['lines'])->whereIn('kind', ['basic', 'earning', 'arrears']))
    @php($deductions = collect($payslip['lines'])->whereIn('kind', ['deduction', 'tax']))
    <div class="py-6"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6 space-y-4 text-sm">
            <div class="grid gap-2 sm:grid-cols-2">
                <div><span class="text-gray-500">Employee</span><p class="font-medium">{{ $employee['name'] }} ({{ $employee['code'] }})</p></div>
                <div><span class="text-gray-500">Designation</span><p>{{ $employee['designation'] ?? '—' }}</p></div>
                <div><span class="text-gray-500">National ID</span><p>{{ $employee['national_id'] ?? '—' }}</p></div>
                <div><span class="text-gray-500">Days paid</span><p>{{ (float) $payslip['days_paid'] }} of {{ (float) $payslip['days_in_month'] }}</p></div>
                <div><span class="text-gray-500">Bank</span><p>{{ $employee['bank_name'] ? $employee['bank_name'].' '.$employee['bank_account'] : '—' }}</p></div>
            </div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div><p class="mb-1 font-medium">Earnings</p>@foreach ($earnings as $line)<div class="flex justify-between border-t py-1"><span>{{ $line['description'] }}</span><span class="tabular-nums">{{ $fmt($line['amount']) }}</span></div>@endforeach<div class="flex justify-between border-t py-1 font-medium"><span>Gross</span><span class="tabular-nums">{{ $fmt($payslip['gross']) }}</span></div></div>
                <div><p class="mb-1 font-medium">Deductions</p>@foreach ($deductions as $line)<div class="flex justify-between border-t py-1"><span>{{ $line['description'] }}</span><span class="tabular-nums">{{ $fmt($line['amount']) }}</span></div>@endforeach<div class="flex justify-between border-t py-1 font-medium"><span>Total</span><span class="tabular-nums">{{ $fmt((float) $payslip['deductions'] + (float) $payslip['tax']) }}</span></div></div>
            </div>
            @php($employerLines = collect($payslip['lines'])->where('kind', 'employer'))
            @if($employerLines->isNotEmpty())
                <div><p class="mb-1 font-medium">Employer contributions <span class="font-normal text-gray-500">(paid by the company, not deducted from pay)</span></p>@foreach ($employerLines as $line)<div class="flex justify-between border-t py-1"><span>{{ $line['description'] }}</span><span class="tabular-nums">{{ $fmt($line['amount']) }}</span></div>@endforeach</div>
            @endif
            <div class="flex justify-between border-t pt-3 text-lg font-semibold"><span>Net pay</span><span class="tabular-nums">{{ $fmt($payslip['net']) }}</span></div>
        </div>
    </div></div>
</x-accounting::app-layout>
