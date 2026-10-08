<x-accounting::app-layout title="Arrears">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Arrears</h2><div class="flex gap-2">@can('payroll.manage')<a href="{{ route('accounting.payroll.bulk') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Bulk changes</a>@endcan<a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    @php($cents = fn ($v) => number_format($v / 100, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Back pay of a raise: worked out from the payslips already posted, approved, and paid with a payroll run as its own line.</p>
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">Arrears (not cancelled)</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $money($totals['amount']) }}</p><p class="text-xs text-gray-500">{{ $totals['count'] }} records</p></div>
            @foreach ($totals['by_status'] as $status => $amount)@if($status !== 'cancelled')<div class="bg-white shadow rounded-lg p-5"><p class="text-sm text-gray-500">{{ $status }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $money($amount) }}</p></div>@endif @endforeach
        </div>
        @can('payroll.run')
            <form method="POST" action="{{ route('accounting.payroll.arrears.preview') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
                @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Work out arrears</h3>
                <label class="text-sm"><span class="text-gray-700">First month covered</span><input type="date" name="from_month" value="{{ old('from_month', $defaults['from_month']) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Paid with the payroll of</span><input type="date" name="payment_month" value="{{ old('payment_month', $defaults['payment_month']) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Grade (when nobody is picked)</span><select name="salary_grade_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Every active employee</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}" @selected((string) old('salary_grade_id') === (string) $grade->id)>{{ $grade->code }} {{ $grade->name }}</option>@endforeach</select></label>
                <label class="text-sm"><span class="text-gray-700">Notes</span><input name="notes" value="{{ old('notes') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <details class="rounded-md border p-3 text-sm md:col-span-4" x-data>
                    <summary class="cursor-pointer">Pick employees</summary>
                    <div class="mt-2 flex gap-2"><button type="button" class="px-2 py-1 border border-gray-300 rounded text-xs" @click="$el.closest('details').querySelectorAll('input[type=checkbox]').forEach(box => box.checked = true)">All</button><button type="button" class="px-2 py-1 border border-gray-300 rounded text-xs" @click="$el.closest('details').querySelectorAll('input[type=checkbox]').forEach(box => box.checked = false)">None</button></div>
                    <div class="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">@foreach ($employees as $employee)<label class="flex items-center gap-2"><input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}">{{ $employee->code }} {{ $employee->name }}</label>@endforeach</div>
                </details>
                <div class="flex gap-2 md:col-span-4"><button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Preview</button>
                    @if($preview)<button formaction="{{ route('accounting.payroll.arrears.store') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Create for approval</button>@endif</div>
                @if($preview)
                    <div class="overflow-x-auto md:col-span-4"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Employee</th><th>From</th><th>To</th><th>Months</th><th class="text-right">Arrears</th></tr></thead><tbody>
                        @forelse ($preview as $row)
                            <tr class="border-t align-top"><td class="py-1">{{ $row['code'] }} {{ $row['name'] }}</td><td>{{ $row['from_month'] }}</td><td>{{ $row['to_month'] }}</td><td class="text-xs text-gray-500">{{ collect($row['months'])->map(fn ($month) => $month['month'].': '.$cents($month['paid']).' → '.$cents($month['due']))->implode(' · ') }}</td><td class="text-right tabular-nums">{{ $money($row['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-center text-gray-500">No arrears are due for those months.</td></tr>
                        @endforelse
                    </tbody></table><p class="mt-2 text-xs text-gray-500">Only months with a posted or paid payslip count; months already claimed are skipped.</p></div>
                @endif
            </form>
        @endcan
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <div class="flex items-center justify-between p-4"><h3 class="font-semibold text-gray-800">Register</h3>@can('payroll.post')<form method="POST" action="{{ route('accounting.payroll.arrears.approve-all') }}" onsubmit="return confirm('Approve every draft?')">@csrf<button class="px-3 py-1 border border-gray-300 rounded text-xs uppercase tracking-widest hover:bg-gray-50">Approve all drafts</button></form>@endcan</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Employee</th><th class="py-2 px-3 text-left font-medium text-gray-600">Covers</th><th class="py-2 px-3 text-left font-medium text-gray-600">Paid with</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t"><td class="py-2 px-3">{{ $row['employee_code'] }} {{ $row['employee_name'] }}</td><td class="py-2 px-3">{{ $row['from_month'] }}{{ $row['to_month'] !== $row['from_month'] ? ' – '.$row['to_month'] : '' }}</td><td class="py-2 px-3">{{ $row['payment_month'] }}</td><td class="py-2 px-3">{{ $row['status'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['amount']) }}</td>
                        <td class="py-2 px-3 text-right">@can('payroll.post')
                            @if($row['status'] === 'draft')<form method="POST" action="{{ route('accounting.payroll.arrears.approve', $row['id']) }}" class="inline">@csrf<button class="text-indigo-700 hover:underline">Approve</button></form>@endif
                            @if(in_array($row['status'], ['draft', 'approved'], true))<form method="POST" action="{{ route('accounting.payroll.arrears.cancel', $row['id']) }}" class="inline" onsubmit="return confirm('Cancel these arrears?')">@csrf<button class="ml-2 text-red-700 hover:underline">Cancel</button></form>@endif
                        @endcan</td></tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">No arrears yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div></div>
</x-accounting::app-layout>
