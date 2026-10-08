<x-accounting::app-layout title="Contributions">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Contributions</h2><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></x-slot>
    @php($e = $editing)
    @php($v = fn ($name, $default = '') => old($name, $e[$name] ?? $default))
    @php($rate = fn ($s, $side) => $s['base'] === 'fixed' ? number_format((float) $s[$side.'_fixed'], 2) : (float) $s[$side.'_rate'].'%')
    <div class="py-6"><div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">EOBI, PESSI/SESSI, provident fund: the employee's share comes out of pay, the employer's share is a cost owed to the fund.</p>
        <div class="bg-white shadow rounded-lg p-5 overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Code</th><th>Name</th><th>Base</th><th class="text-right">Employee</th><th class="text-right">Employer</th><th class="text-right">Ceiling</th><th>Who</th><th></th></tr></thead><tbody>
            @forelse ($schemes as $row)
                <tr class="border-t"><td class="py-2 font-medium">{{ $row['code'] }}</td><td>{{ $row['name'] }} @unless($row['is_active'])<span class="ml-1 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactive</span>@endunless</td><td>{{ $row['base'] }}</td><td class="text-right tabular-nums">{{ $rate($row, 'employee') }}</td><td class="text-right tabular-nums">{{ $rate($row, 'employer') }}</td><td class="text-right tabular-nums">{{ $row['ceiling'] ? number_format((float) $row['ceiling'], 2) : '—' }}</td><td>{{ $row['applies_to_all'] ? 'everybody' : $row['employees'].' employees' }}</td>
                    <td class="text-right">@can('payroll.manage')<a class="text-indigo-700 hover:underline" href="{{ route('accounting.payroll.schemes.index', ['edit' => $row['id']]) }}">Edit</a><form method="POST" action="{{ route('accounting.payroll.schemes.destroy', $row['id']) }}" class="inline" onsubmit="return confirm('Delete this scheme?')">@csrf @method('DELETE')<button class="ml-2 text-red-700 hover:underline">Delete</button></form>@endcan</td></tr>
            @empty
                <tr><td colspan="8" class="py-4 text-center text-gray-500">No schemes yet.</td></tr>
            @endforelse
        </tbody></table></div>
        @can('payroll.manage')
            <form method="POST" action="{{ $e ? route('accounting.payroll.schemes.update', $e['id']) : route('accounting.payroll.schemes.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4" x-data="{ base: '{{ $v('base', 'basic') }}' }">
                @csrf @if($e) @method('PUT') @endif
                <h3 class="font-semibold text-gray-800 md:col-span-4">{{ $e ? 'Edit '.$e['code'] : 'Add a scheme' }}</h3>
                <label class="text-sm"><span class="text-gray-700">Code</span><input name="code" value="{{ $v('code') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Name</span><input name="name" value="{{ $v('name') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Calculated on</span><select name="base" x-model="base" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="basic">Percent of basic</option><option value="gross">Percent of gross</option><option value="fixed">Fixed amount a month</option></select></label>
                <div x-show="base !== 'fixed'" class="contents"><label class="text-sm"><span class="text-gray-700">Employee %</span><input type="number" step="any" name="employee_rate" value="{{ $v('employee_rate') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="text-sm"><span class="text-gray-700">Employer %</span><input type="number" step="any" name="employer_rate" value="{{ $v('employer_rate') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="text-sm"><span class="text-gray-700">Ceiling (most of the base that counts)</span><input type="number" step="any" name="ceiling" value="{{ $v('ceiling') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label></div>
                <div x-show="base === 'fixed'" class="contents"><label class="text-sm"><span class="text-gray-700">Employee pays</span><input type="number" step="any" name="employee_fixed" value="{{ $v('employee_fixed') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="text-sm"><span class="text-gray-700">Employer pays</span><input type="number" step="any" name="employer_fixed" value="{{ $v('employer_fixed') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label></div>
                @foreach ([['employee_account_id', "Owed to (employees' share)", 'LIABILITY'], ['employer_expense_account_id', "Employer's cost (expense)", 'EXPENSE'], ['employer_liability_account_id', "Owed to (employer's share)", 'LIABILITY']] as [$name, $label, $type])
                    <label class="text-sm"><span class="text-gray-700">{{ $label }}</span><select name="{{ $name }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($accounts as $account)@if($account['type'] === $type)<option value="{{ $account['id'] }}" @selected((string) $v($name) === (string) $account['id'])>{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endif @endforeach</select></label>
                @endforeach
                <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="applies_to_all" value="0"><input type="checkbox" name="applies_to_all" value="1" @checked($v('applies_to_all', false))> Applies to every employee</label>
                <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="on_arrears" value="0"><input type="checkbox" name="on_arrears" value="1" @checked($v('on_arrears', false))> Also on arrears</label>
                <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($v('is_active', true))> Active</label>
                <div class="pt-6"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">{{ $e ? 'Save' : 'Add' }}</button>@if($e) <a href="{{ route('accounting.payroll.schemes.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Cancel</a>@endif</div>
            </form>
            @if(count($schemes))
                <form method="POST" action="#" class="bg-white shadow rounded-lg p-5 space-y-3 text-sm" x-data="{ scheme: '' }" x-bind:action="scheme ? '{{ url(trim(config('accounting.route_prefix', 'accounting'), '/').'/payroll/schemes') }}/' + scheme + '/assign' : '#'">
                    @csrf <h3 class="font-semibold text-gray-800">Give a scheme to employees</h3>
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="text-sm"><span class="text-gray-700">Scheme</span><select x-model="scheme" class="mt-1 block w-64 border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($schemes as $row)<option value="{{ $row['id'] }}">{{ $row['code'] }} {{ $row['name'] }}</option>@endforeach</select></label>
                        <label class="text-sm"><span class="text-gray-700">Action</span><select name="mode" class="mt-1 block w-40 border-gray-300 rounded-md shadow-sm text-sm"><option value="assign">Give it</option><option value="remove">Take it away</option></select></label>
                        <button :disabled="!scheme" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Apply</button>
                    </div>
                    <details class="rounded-md border p-3"><summary class="cursor-pointer">Pick employees (none picked = every active employee)</summary><div class="mt-2 grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">@foreach ($employees as $row)<label class="flex items-center gap-2"><input type="checkbox" name="employee_ids[]" value="{{ $row->id }}">{{ $row->code }} {{ $row->name }}</label>@endforeach</div></details>
                </form>
            @endif
        @endcan
    </div></div>
</x-accounting::app-layout>
