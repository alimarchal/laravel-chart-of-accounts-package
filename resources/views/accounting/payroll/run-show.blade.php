<x-accounting::app-layout title="Payroll run">
    <x-slot name="header">
        <div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll {{ $run['period_month'] }} <span class="ml-2 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-700">{{ $run['status'] }}</span></h2>
            <div class="flex gap-2">
                @if($run['status'] === 'draft')
                    @can('payroll.run')<form method="POST" action="{{ route('accounting.payroll.runs.recalculate', $run['id']) }}">@csrf<button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Recalculate</button></form>@endcan
                    @can('payroll.post')<form method="POST" action="{{ route('accounting.payroll.runs.post', $run['id']) }}" onsubmit="return confirm('Post this payroll to the books?')">@csrf<button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Post</button></form>@endcan
                    @can('payroll.run')<form method="POST" action="{{ route('accounting.payroll.runs.destroy', $run['id']) }}" onsubmit="return confirm('Delete this draft run?')">@csrf @method('DELETE')<button class="px-3 py-2 text-red-700 text-xs font-semibold uppercase tracking-widest hover:underline">Delete</button></form>@endcan
                @endif
                @if(in_array($run['status'], ['posted', 'paid'], true))@can('payroll.void')<form method="POST" action="{{ route('accounting.payroll.runs.void', $run['id']) }}" onsubmit="return confirm('Void this payroll? Its entries will be reversed.')">@csrf<button class="px-3 py-2 text-red-700 text-xs font-semibold uppercase tracking-widest hover:underline">Void</button></form>@endcan @endif
                <a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a>
            </div></div>
    </x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <div class="grid gap-4 sm:grid-cols-4">
            @foreach (['Gross' => 'gross', 'Deductions' => 'deductions', 'Income tax' => 'tax', 'Net pay' => 'net'] as $label => $key)
                <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($run[$key]) }}</p></div>
            @endforeach
        </div>
        <p class="text-sm text-gray-600">@if($run['journal_entry_id'])Salary entry <a class="hover:underline" href="{{ route('accounting.journal-entries.show', $run['journal_entry_id']) }}">#{{ $run['journal_entry_id'] }}</a> on {{ $run['posted_on'] }}. @endif @if($run['payment_entry_id'])Payment entry <a class="hover:underline" href="{{ route('accounting.journal-entries.show', $run['payment_entry_id']) }}">#{{ $run['payment_entry_id'] }}</a> on {{ $run['paid_on'] }}.@endif</p>
        @if($run['status'] === 'posted')@can('payroll.post')
            <form method="POST" action="{{ route('accounting.payroll.runs.pay', $run['id']) }}" class="bg-white shadow rounded-lg p-5 flex flex-wrap items-end gap-3">
                @csrf <h3 class="font-semibold text-gray-800 w-full">Pay salaries</h3>
                <label class="text-sm"><span class="text-gray-700">Paid from</span><select name="account_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose bank or cash…</option>@foreach ($accounts as $account)@if($account['type'] === 'ASSET')<option value="{{ $account['id'] }}">{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endif @endforeach</select></label>
                <label class="text-sm"><span class="text-gray-700">Date</span><input type="date" name="date" value="{{ $today }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <button class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Pay {{ $fmt($run['net']) }}</button>
            </form>
        @endcan @endif
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Employee</th><th class="py-2 px-3 text-right font-medium text-gray-600">Days</th><th class="py-2 px-3 text-right font-medium text-gray-600">Basic</th><th class="py-2 px-3 text-right font-medium text-gray-600">Gross</th><th class="py-2 px-3 text-right font-medium text-gray-600">Deductions</th><th class="py-2 px-3 text-right font-medium text-gray-600">Tax</th><th class="py-2 px-3 text-right font-medium text-gray-600">Net</th></tr></thead>
            <tbody>
            @forelse ($payslips as $slip)
                <tr class="border-t"><td class="py-2 px-3"><a class="text-indigo-700 hover:underline" href="{{ route('accounting.payroll.payslips.show', [$run['id'], $slip['id']]) }}">{{ $slip['employee_code'] }} · {{ $slip['employee_name'] }}</a></td><td class="py-2 px-3 text-right">{{ (float) $slip['days_paid'] }}/{{ (float) $slip['days_in_month'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($slip['basic']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($slip['gross']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($slip['deductions']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($slip['tax']) }}</td><td class="py-2 px-3 text-right font-medium tabular-nums">{{ $fmt($slip['net']) }}</td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">No employees were employed in this month.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
