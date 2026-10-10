<x-accounting::app-layout title="Bonuses and one-off pay">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Bonuses and one-off pay</h2><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    @php($input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm')
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">A bonus, an extra allowance or a fine for one month, for one employee or for many; the month's payroll run takes it up.</p>
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label><span class="text-gray-700">Month</span><input type="month" name="month" value="{{ $month }}" class="{{ $input }}"></label>
            <label><span class="text-gray-700">Status</span><select name="status" class="{{ $input }}">@foreach (['' => 'all', 'open' => 'open', 'included' => 'included', 'cancelled' => 'cancelled'] as $value => $label)<option value="{{ $value }}" @selected((string) $status === (string) $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Show</button>
        </form>
        @can('payroll.manage')
            <div class="grid gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('accounting.payroll.adjustments.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-3 md:grid-cols-2">
                    @csrf <h3 class="font-semibold text-gray-800 md:col-span-2">One employee</h3>
                    <label class="text-sm"><span class="text-gray-700">Employee</span><select name="employee_id" class="{{ $input }}"><option value="">Choose…</option>@foreach ($employees as $row)<option value="{{ $row['id'] }}" @selected((string) old('employee_id') === (string) $row['id'])>{{ $row['code'] }} {{ $row['name'] }}</option>@endforeach</select></label>
                    <label class="text-sm"><span class="text-gray-700">Month</span><input type="date" name="month" value="{{ old('month', $month.'-01') }}" class="{{ $input }}"></label>
                    <label class="text-sm md:col-span-2"><span class="text-gray-700">What for</span><input name="description" value="{{ old('description') }}" class="{{ $input }}"></label>
                    <label class="text-sm"><span class="text-gray-700">Pay component (optional)</span><select name="pay_component_id" class="{{ $input }}"><option value="">None: a plain bonus or fine</option>@foreach ($components as $row)<option value="{{ $row['id'] }}">{{ $row['code'] }} {{ $row['name'] }} ({{ $row['kind'] }})</option>@endforeach</select></label>
                    <label class="text-sm"><span class="text-gray-700">Amount</span><input type="number" step="any" name="amount" value="{{ old('amount') }}" class="{{ $input }}"></label>
                    <label class="text-sm"><span class="text-gray-700">Pays or takes (no component)</span><select name="kind" class="{{ $input }}"><option value="earning">Earning (bonus)</option><option value="deduction">Deduction (fine)</option></select></label>
                    <label class="text-sm"><span class="text-gray-700">Account (blank = bonus account)</span><select name="account_id" class="{{ $input }}"><option value="">Default</option>@foreach ($accounts as $row)<option value="{{ $row['id'] }}">{{ $row['account_code'] }} {{ $row['account_name'] }}</option>@endforeach</select></label>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="taxable" value="0"><input type="checkbox" name="taxable" value="1" checked>Taxable</label>
                    <div class="md:col-span-2"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Add</button></div>
                </form>
                <form method="POST" action="{{ route('accounting.payroll.adjustments.bulk') }}" class="bg-white shadow rounded-lg p-5 grid gap-3 md:grid-cols-2">
                    @csrf <h3 class="font-semibold text-gray-800 md:col-span-2">Every active employee</h3>
                    <label class="text-sm"><span class="text-gray-700">Month</span><input type="date" name="month" value="{{ $month }}-01" class="{{ $input }}"></label>
                    <label class="text-sm"><span class="text-gray-700">What for</span><input name="description" class="{{ $input }}"></label>
                    <label class="text-sm"><span class="text-gray-700">How much</span><select name="method" class="{{ $input }}"><option value="fixed">The same amount for everybody</option><option value="percent_of_basic">A percent of each basic salary</option></select></label>
                    <label class="text-sm"><span class="text-gray-700">Amount or percent</span><input type="number" step="any" name="value" class="{{ $input }}"></label>
                    <label class="text-sm"><span class="text-gray-700">Pay component (optional)</span><select name="pay_component_id" class="{{ $input }}"><option value="">None: a plain bonus</option>@foreach ($components as $row)@if($row['kind'] === 'earning')<option value="{{ $row['id'] }}">{{ $row['code'] }} {{ $row['name'] }}</option>@endif @endforeach</select></label>
                    <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="taxable" value="0"><input type="checkbox" name="taxable" value="1" checked>Taxable</label>
                    <div class="md:col-span-2"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Add for everybody</button></div>
                </form>
            </div>
        @endcan
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Employee</th><th class="py-2 px-3 text-left font-medium text-gray-600">Month</th><th class="py-2 px-3 text-left font-medium text-gray-600">What for</th><th class="py-2 px-3 text-left font-medium text-gray-600">Kind</th><th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($adjustments as $row)
                <tr class="border-t"><td class="py-2 px-3">{{ $row['employee_code'] }} {{ $row['employee_name'] }}</td><td class="py-2 px-3">{{ $row['month'] }}</td><td class="py-2 px-3">{{ $row['description'] }}@if(! $row['taxable'] && $row['kind'] === 'earning') <span class="text-xs text-gray-500">(not taxed)</span>@endif</td><td class="py-2 px-3">{{ $row['kind'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $money($row['amount']) }}</td><td class="py-2 px-3">{{ $row['status'] }}</td>
                    <td class="py-2 px-3 text-right">@if($row['status'] === 'open')@can('payroll.manage')<form method="POST" action="{{ route('accounting.payroll.adjustments.cancel', $row['id']) }}" onsubmit="return confirm('Cancel this?')">@csrf<button class="text-red-700 hover:underline">Cancel</button></form>@endcan @endif</td></tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-gray-500">Nothing for {{ $month }}.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
