<x-accounting::app-layout title="Bulk changes">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Bulk changes</h2><div class="flex gap-2"><a href="{{ route('accounting.payroll.arrears.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Arrears</a><a href="{{ route('accounting.payroll.grades.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Grades</a><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></div></x-slot>
    @php($money = fn ($v) => number_format((float) $v, 2))
    @php($who = fn () => view('accounting::payroll.partials.who', ['employees' => $employees, 'grades' => $grades])->render())
    <div class="py-6"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Set pay up for many employees at once. Nobody picked means everybody on the grade chosen, or every active employee.</p>

        <form method="POST" action="{{ route('accounting.payroll.bulk.components') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
            @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Give an allowance or deduction to many</h3>
            <label class="text-sm"><span class="text-gray-700">Component</span><select name="pay_component_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($components as $row)<option value="{{ $row->id }}">{{ $row->code }} {{ $row->name }}</option>@endforeach</select></label>
            <label class="text-sm"><span class="text-gray-700">Action</span><select name="mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="assign">Give it</option><option value="remove">Take it away</option></select></label>
            <label class="text-sm"><span class="text-gray-700">Their own value (blank = the component's)</span><input type="number" step="any" name="value" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            <div class="md:col-span-4">{!! $who() !!}</div>
            <div><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Apply</button></div>
        </form>

        <form method="POST" action="{{ route('accounting.payroll.revisions.preview') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4" id="raise">
            @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Raise salaries</h3>
            <label class="text-sm"><span class="text-gray-700">How</span><select name="mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="percent" @selected(old('mode') === 'percent')>Raise by a percent</option><option value="increase" @selected(old('mode') === 'increase')>Add an amount</option><option value="set" @selected(old('mode') === 'set')>Set everybody to an amount</option></select></label>
            <label class="text-sm"><span class="text-gray-700">Percent or amount</span><input type="number" step="any" name="value" value="{{ old('value') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            <label class="text-sm"><span class="text-gray-700">Applies from</span><input type="date" name="effective_from" value="{{ old('effective_from', $today) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            <label class="text-sm"><span class="text-gray-700">Round to the nearest</span><input type="number" min="1" name="round_to" value="{{ old('round_to', 1) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            <label class="text-sm md:col-span-2"><span class="text-gray-700">Reason</span><input name="reason" value="{{ old('reason') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            <div class="md:col-span-4">{!! $who() !!}</div>
            <div class="flex gap-2"><button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Preview</button>
                @if($preview)<button formaction="{{ route('accounting.payroll.revisions.apply') }}" onclick="return confirm('Apply to {{ count($preview) }} employees?')" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Apply</button>@endif</div>
            @if($preview)
                <div class="overflow-x-auto md:col-span-4"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Employee</th><th class="text-right">Now</th><th class="text-right">New</th><th class="text-right">Difference</th></tr></thead><tbody>
                    @forelse ($preview as $row)<tr class="border-t"><td class="py-1">{{ $row['code'] }} {{ $row['name'] }}</td><td class="text-right tabular-nums">{{ $money($row['old_salary']) }}</td><td class="text-right tabular-nums">{{ $money($row['new_salary']) }}</td><td class="text-right tabular-nums">{{ $money($row['difference']) }}</td></tr>@empty<tr><td colspan="4" class="py-3 text-center text-gray-500">Nobody matches.</td></tr>@endforelse
                </tbody></table><p class="mt-2 text-xs text-gray-500">A raise from a past date does not change payslips already posted: use Arrears to pay the difference.</p></div>
            @endif
        </form>

        <form method="POST" action="#" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4" x-data="{ grade: '' }" x-bind:action="grade ? '{{ url(trim(config('accounting.route_prefix', 'accounting'), '/').'/payroll/grades') }}/' + grade + '/assign' : '#'">
            @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Put employees on a grade</h3>
            <label class="text-sm"><span class="text-gray-700">Grade</span><select x-model="grade" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($grades as $row)<option value="{{ $row->id }}">{{ $row->code }} {{ $row->name }} ({{ $money($row->base_salary) }})</option>@endforeach</select></label>
            <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="apply_salary" value="0"><input type="checkbox" name="apply_salary" value="1"> Also move their salary to the grade's</label>
            <label class="text-sm"><span class="text-gray-700">Salary applies from</span><input type="date" name="effective_from" value="{{ $today }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
            <div class="md:col-span-4">{!! $who() !!}</div>
            <div><button :disabled="!grade" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Move</button></div>
        </form>
    </div></div>
</x-accounting::app-layout>
