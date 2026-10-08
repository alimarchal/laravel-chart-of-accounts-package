<x-accounting::app-layout title="Loans and advances">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Loans and advances</h2><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Paid out to employees and recovered by instalments from their salary.</p>
        <div class="flex gap-2">@foreach (['' => 'all', 'draft' => 'draft', 'active' => 'active', 'closed' => 'closed'] as $value => $label)<a class="px-3 py-1 border rounded text-xs {{ (string) $status === (string) $value ? 'bg-blue-950 text-white' : 'border-gray-300' }}" href="{{ route('accounting.payroll.loans.index', $value ? ['status' => $value] : []) }}">{{ $label }}</a>@endforeach</div>
        @can('payroll.manage')
            <form method="POST" action="{{ route('accounting.payroll.loans.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
                @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">New loan or advance</h3>
                <label class="text-sm"><span class="text-gray-700">Employee</span><select name="employee_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($employees as $row)<option value="{{ $row['id'] }}" @selected((string) old('employee_id') === (string) $row['id'])>{{ $row['code'] }} {{ $row['name'] }}</option>@endforeach</select></label>
                <label class="text-sm"><span class="text-gray-700">Kind</span><select name="kind" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="loan">Loan</option><option value="advance">Salary advance</option></select></label>
                <label class="text-sm"><span class="text-gray-700">Amount</span><input type="number" step="any" name="principal" value="{{ old('principal') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Instalments</span><input type="number" min="1" name="installments" value="{{ old('installments', 12) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">First salary it comes out of</span><input type="date" name="start_month" value="{{ old('start_month', $today) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm md:col-span-2"><span class="text-gray-700">Notes</span><input name="notes" value="{{ old('notes') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <div class="pt-6"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Record</button></div>
            </form>
        @endcan
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Employee</th><th class="py-2 px-3 text-left font-medium text-gray-600">Kind</th><th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th><th class="py-2 px-3 text-right font-medium text-gray-600">Left to recover</th><th class="py-2 px-3 text-left font-medium text-gray-600">From</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($loans as $loan)
                <tr class="border-t"><td class="py-2 px-3">{{ $loan['employee_code'] }} {{ $loan['employee_name'] }}</td><td class="py-2 px-3">{{ $loan['kind'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($loan['principal']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($loan['outstanding']) }}</td><td class="py-2 px-3">{{ $loan['start_month'] }} · {{ $loan['installments'] }}x</td><td class="py-2 px-3">{{ $loan['status'] }}</td>
                    <td class="py-2 px-3 text-right space-x-2">
                        @if($loan['status'] === 'active')@can('payroll.manage')<form method="POST" action="{{ route('accounting.payroll.loans.skip', $loan['id']) }}" class="inline" onsubmit="return confirm('Move the next instalment to the end?')">@csrf<button class="text-indigo-700 hover:underline">Skip next</button></form>@endcan @endif
                        @if($loan['status'] === 'draft')@can('payroll.manage')<form method="POST" action="{{ route('accounting.payroll.loans.cancel', $loan['id']) }}" class="inline" onsubmit="return confirm('Cancel this loan?')">@csrf<button class="text-red-700 hover:underline">Cancel</button></form>@endcan @endif</td></tr>
                <tr><td colspan="7" class="px-3 pb-3 bg-gray-50"><div class="flex flex-wrap gap-2 text-xs py-2">@foreach ($loan['schedule'] as $row)<span class="rounded border px-2 py-1 {{ $row['status'] === 'cancelled' ? 'line-through opacity-50' : '' }}">{{ $row['due_month'] }} {{ $money($row['amount']) }} <em>{{ $row['status'] }}</em></span>@endforeach</div>
                    @can('payroll.post')@if(in_array($loan['status'], ['draft', 'active'], true))
                        <form method="POST" action="{{ route('accounting.payroll.loans.'.($loan['status'] === 'draft' ? 'disburse' : 'settle'), $loan['id']) }}" class="flex flex-wrap items-center gap-2" @if($loan['status'] === 'active') onsubmit="return confirm('The employee pays back what is left in cash?')" @endif>
                            @csrf <select name="account_id" class="border-gray-300 rounded-md shadow-sm text-sm w-64"><option value="">Bank or cash account…</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}">{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endforeach</select>
                            <input type="date" name="date" value="{{ $today }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <button class="inline-flex items-center px-3 py-1 {{ $loan['status'] === 'draft' ? 'bg-green-700 text-white' : 'border border-gray-300' }} rounded-md font-semibold text-xs uppercase tracking-widest">{{ $loan['status'] === 'draft' ? 'Pay out '.$money($loan['principal']) : 'Settle '.$money($loan['outstanding']) }}</button>
                        </form>
                    @endif @endcan</td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No loans yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
