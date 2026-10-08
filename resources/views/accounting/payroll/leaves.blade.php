<x-accounting::app-layout title="Leave">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Leave</h2><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></x-slot>
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Leave taken and yearly balances; unpaid leave comes off the salary by the day.</p>
        @can('payroll.manage')
            <form method="POST" action="{{ route('accounting.payroll.leaves.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
                @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Record leave</h3>
                <label class="text-sm"><span class="text-gray-700">Employee</span><select name="employee_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($employees as $row)<option value="{{ $row['id'] }}" @selected((string) old('employee_id') === (string) $row['id'])>{{ $row['code'] }} {{ $row['name'] }}</option>@endforeach</select></label>
                <label class="text-sm"><span class="text-gray-700">Leave type</span><select name="leave_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($types as $row)@if($row['is_active'])<option value="{{ $row['id'] }}" @selected((string) old('leave_type_id') === (string) $row['id'])>{{ $row['name'] }}{{ $row['is_paid'] ? '' : ' (unpaid)' }}</option>@endif @endforeach</select></label>
                <label class="text-sm"><span class="text-gray-700">From</span><input type="date" name="from_date" value="{{ old('from_date', $today) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">To</span><input type="date" name="to_date" value="{{ old('to_date', $today) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Days (blank = all the dates; 0.5 = half day)</span><input type="number" step="0.5" name="days" value="{{ old('days') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm md:col-span-2"><span class="text-gray-700">Notes</span><input name="notes" value="{{ old('notes') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <div class="pt-6"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Record</button></div>
            </form>
        @endcan
        <div class="bg-white shadow rounded-lg p-5 overflow-x-auto"><h3 class="font-semibold text-gray-800 mb-2">Leaves</h3><table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Employee</th><th>Type</th><th>From</th><th>To</th><th class="text-right">Days</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse ($leaves as $row)
                <tr class="border-t"><td class="py-2">{{ $row['employee_code'] }} {{ $row['employee_name'] }}</td><td>{{ $row['leave_type'] }}{{ $row['is_paid'] ? '' : ' (unpaid)' }}</td><td>{{ $row['from_date'] }}</td><td>{{ $row['to_date'] }}</td><td class="text-right tabular-nums">{{ (float) $row['days'] }}</td><td>{{ $row['status'] }}</td>
                    <td class="text-right">@can('payroll.manage')@if($row['status'] === 'approved')<form method="POST" action="{{ route('accounting.payroll.leaves.cancel', $row['id']) }}" class="inline" onsubmit="return confirm('Cancel this leave?')">@csrf<button class="text-red-700 hover:underline">Cancel</button></form>@endif @endcan</td></tr>
            @empty
                <tr><td colspan="7" class="py-4 text-center text-gray-500">No leave recorded yet.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="bg-white shadow rounded-lg p-5 overflow-x-auto"><div class="flex items-center justify-between mb-2"><h3 class="font-semibold text-gray-800">Balances {{ $year }}</h3><div class="flex gap-2"><a class="px-2 py-1 border border-gray-300 rounded text-xs" href="{{ route('accounting.payroll.leaves.index', ['year' => $year - 1]) }}">{{ $year - 1 }}</a><a class="px-2 py-1 border border-gray-300 rounded text-xs" href="{{ route('accounting.payroll.leaves.index', ['year' => $year + 1]) }}">{{ $year + 1 }}</a></div></div>
            <table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Employee</th><th>Type</th><th class="text-right">Entitled</th><th class="text-right">Taken</th><th class="text-right">Balance</th></tr></thead><tbody>
                @foreach ($balances as $row)@if($row['entitlement'] > 0 || $row['taken'] > 0)<tr class="border-t"><td class="py-1">{{ $row['employee_code'] }} {{ $row['employee_name'] }}</td><td>{{ $row['leave_type'] }}</td><td class="text-right tabular-nums">{{ $row['entitlement'] ?: '—' }}</td><td class="text-right tabular-nums">{{ $row['taken'] }}</td><td class="text-right tabular-nums">{{ $row['entitlement'] ? $row['balance'] : '—' }}</td></tr>@endif @endforeach
            </tbody></table></div>
        <div class="bg-white shadow rounded-lg p-5 space-y-4 text-sm"><h3 class="font-semibold text-gray-800">Leave types</h3>
            <table class="w-full"><thead><tr class="text-left text-gray-500"><th class="py-1">Code</th><th>Name</th><th>Paid</th><th class="text-right">Days a year</th><th></th></tr></thead><tbody>
                @foreach ($types as $row)<tr class="border-t"><td class="py-1 font-medium">{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['is_paid'] ? 'yes' : 'no' }}</td><td class="text-right tabular-nums">{{ (float) $row['annual_days'] ?: '—' }}</td><td class="text-right">@can('payroll.manage')<form method="POST" action="{{ route('accounting.payroll.leave-types.destroy', $row['id']) }}" class="inline" onsubmit="return confirm('Delete this leave type?')">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Delete</button></form>@endcan</td></tr>@endforeach
            </tbody></table>
            @can('payroll.manage')
                <form method="POST" action="{{ route('accounting.payroll.leave-types.store') }}" class="grid gap-3 md:grid-cols-5">
                    @csrf
                    <label class="text-sm"><span class="text-gray-700">Code</span><input name="code" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="text-sm"><span class="text-gray-700">Name</span><input name="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="text-sm"><span class="text-gray-700">Days a year (0 = not limited)</span><input type="number" step="0.5" name="annual_days" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="is_paid" value="0"><input type="checkbox" name="is_paid" value="1" checked> Paid leave</label>
                    <div class="pt-6"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Add type</button></div>
                </form>
            @endcan
        </div>
    </div></div>
</x-accounting::app-layout>
