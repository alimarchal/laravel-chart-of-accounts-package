<x-accounting::app-layout title="Attendance">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Attendance</h2><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></x-slot>
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Absent days come off the salary by the day; overtime hours are paid to employees flagged for overtime.</p>
        <form method="GET" action="{{ route('accounting.payroll.attendance.index') }}" class="flex items-end gap-2"><label class="text-sm"><span class="text-gray-700">Month</span><input type="month" name="month" value="{{ $month }}" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"></label><button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Show</button></form>
        <form method="POST" action="{{ route('accounting.payroll.attendance.store') }}" class="bg-white shadow rounded-lg p-5 overflow-x-auto">
            @csrf <input type="hidden" name="month" value="{{ $month }}-01">
            <table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Employee</th><th class="w-28">Absent days</th><th class="w-28">Unpaid leave</th><th class="w-28">Overtime h</th><th class="w-28">Holiday OT h</th><th>Notes</th></tr></thead><tbody>
                @forelse ($rows as $i => $row)
                    <tr class="border-t"><td class="py-1">{{ $row['code'] }} {{ $row['name'] }}<input type="hidden" name="rows[{{ $i }}][employee_id]" value="{{ $row['employee_id'] }}"></td>
                        <td><input type="number" step="0.5" min="0" name="rows[{{ $i }}][absent_days]" value="{{ $row['absent_days'] }}" class="w-24 border-gray-300 rounded-md shadow-sm text-sm" @cannot('payroll.manage') disabled @endcannot></td>
                        <td class="text-gray-500">{{ $row['unpaid_leave_days'] > 0 ? $row['unpaid_leave_days'] : '—' }}</td>
                        <td><input type="number" step="0.5" min="0" name="rows[{{ $i }}][overtime_hours]" value="{{ $row['overtime_hours'] }}" class="w-24 border-gray-300 rounded-md shadow-sm text-sm" @if(! $row['overtime_eligible']) disabled @endif></td>
                        <td><input type="number" step="0.5" min="0" name="rows[{{ $i }}][holiday_overtime_hours]" value="{{ $row['holiday_overtime_hours'] }}" class="w-24 border-gray-300 rounded-md shadow-sm text-sm" @if(! $row['overtime_eligible']) disabled @endif></td>
                        <td><input name="rows[{{ $i }}][notes]" value="{{ $row['notes'] }}" class="w-full border-gray-300 rounded-md shadow-sm text-sm"></td></tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-gray-500">Nobody was employed in this month.</td></tr>
                @endforelse
            </tbody></table>
            @can('payroll.manage')@if(count($rows))<div class="mt-4"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Save attendance</button></div>@endif @endcan
        </form>
    </div></div>
</x-accounting::app-layout>
