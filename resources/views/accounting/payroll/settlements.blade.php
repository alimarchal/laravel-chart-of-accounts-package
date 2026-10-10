<x-accounting::app-layout title="Final settlements">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Final settlements</h2><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Gratuity, unused leave and loans owed when an employee leaves, paid as one net amount.</p>
        @can('payroll.manage')
            <form method="POST" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
                @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Work out a settlement</h3>
                <label class="text-sm"><span class="text-gray-700">Employee</span><select name="employee_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($employees as $row)<option value="{{ $row['id'] }}" @selected((string) old('employee_id') === (string) $row['id'])>{{ $row['code'] }} {{ $row['name'] }}</option>@endforeach</select></label>
                <label class="text-sm"><span class="text-gray-700">Last day</span><input type="date" name="leave_date" value="{{ old('leave_date', $today) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Gratuity (blank = by the years served)</span><input type="number" step="any" name="gratuity" value="{{ old('gratuity') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Leave days paid (blank = what is left)</span><input type="number" step="0.5" name="leave_days" value="{{ old('leave_days') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Adjustment (+ pay, − recover)</span><input type="number" step="any" name="adjustment" value="{{ old('adjustment') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm md:col-span-2"><span class="text-gray-700">Notes</span><input name="notes" value="{{ old('notes') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <div class="pt-6 flex gap-2"><button formaction="{{ route('accounting.payroll.settlements.preview') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Preview</button>@if($preview)<button formaction="{{ route('accounting.payroll.settlements.store') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Save draft</button>@endif</div>
                @if($preview)
                    <div class="md:col-span-4"><table class="w-full max-w-md text-sm"><tbody>
                        @foreach ([['Years of service', $preview['service_years']], ['Monthly basic', $money($preview['basic'])], ['Gratuity', $money($preview['gratuity'])], ['Leave pay ('.(float) $preview['leave_days'].' days)', $money($preview['leave_encashment'])], ['Adjustment', $money($preview['adjustment'])], ['Loans recovered', '− '.$money($preview['loan_recovery'])], ['Net settlement', $money($preview['net'])]] as [$label, $value])
                            <tr class="border-t"><td class="py-1">{{ $label }}</td><td class="text-right tabular-nums">{{ $value }}</td></tr>
                        @endforeach
                    </tbody></table>
                    @if(! empty($preview['loans']))<p class="mt-2 text-xs text-gray-500">Open loans recovered: {{ collect($preview['loans'])->map(fn ($loan) => $loan['kind'].' '.$money($loan['outstanding']))->implode(', ') }}</p>@endif</div>
                @endif
            </form>
        @endcan
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Employee</th><th class="py-2 px-3 text-left font-medium text-gray-600">Last day</th><th class="py-2 px-3 text-right font-medium text-gray-600">Gratuity</th><th class="py-2 px-3 text-right font-medium text-gray-600">Leave pay</th><th class="py-2 px-3 text-right font-medium text-gray-600">Loans</th><th class="py-2 px-3 text-right font-medium text-gray-600">Net</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($settlements as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['employee_code'] }} {{ $row['employee_name'] }}</td><td class="py-2 px-3">{{ $row['leave_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['gratuity']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['leave_encashment']) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['loan_recovery']) }}</td><td class="py-2 px-3 text-right font-medium tabular-nums">{{ $money($row['net']) }}</td><td class="py-2 px-3">{{ $row['status'] }}</td>
                    <td class="py-2 px-3 text-right">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                        @if($row['status'] === 'draft')
                            @can('payroll.post')<form method="POST" action="{{ route('accounting.payroll.settlements.post', $row['id']) }}" onsubmit="return confirm('Post this settlement to the books?')">@csrf<button class="px-3 py-1 border border-gray-300 rounded text-xs hover:bg-gray-50">Post</button></form>@endcan
                            @can('payroll.manage')<form method="POST" action="{{ route('accounting.payroll.settlements.destroy', $row['id']) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Delete</button></form>@endcan
                        @endif
                        @if($row['status'] === 'posted')@can('payroll.post')
                            <form method="POST" action="{{ route('accounting.payroll.settlements.pay', $row['id']) }}" class="flex items-center gap-2">@csrf
                                <select name="account_id" class="border-gray-300 rounded-md shadow-sm text-xs w-48"><option value="">Paid from…</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}">{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endforeach</select>
                                <button class="px-3 py-1 bg-green-700 text-white rounded text-xs">Pay</button></form>
                        @endcan @endif
                        @if(in_array($row['status'], ['posted', 'paid'], true))@can('payroll.void')<form method="POST" action="{{ route('accounting.payroll.settlements.void', $row['id']) }}" onsubmit="return confirm('Void this settlement? Its entries are reversed and the loans it recovered open again.')">@csrf<button class="text-red-700 hover:underline">Void</button></form>@endcan @endif
                        </div></td></tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-gray-500">No settlements yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
