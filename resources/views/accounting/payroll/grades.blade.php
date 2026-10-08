<x-accounting::app-layout title="Salary grades">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Salary grades</h2><div class="flex gap-2">@can('payroll.manage')<a href="{{ route('accounting.payroll.bulk') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Bulk changes</a>@endcan<a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></div></x-slot>
    @php($linked = collect($editing['components'] ?? [])->keyBy('pay_component_id'))
    @php($money = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">A grade is a basic salary with its allowances. Change a grade and everybody on it follows in the next payroll calculation.</p>
        <div class="bg-white shadow rounded-lg p-5 overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Code</th><th>Name</th><th class="text-right">Basic salary</th><th class="text-right">Employees</th><th>Allowances</th><th></th></tr></thead><tbody>
            @forelse ($grades as $row)
                <tr class="border-t"><td class="py-2 font-medium">{{ $row['code'] }}</td><td>{{ $row['name'] }} @unless($row['is_active'])<span class="ml-1 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactive</span>@endunless</td><td class="text-right tabular-nums">{{ $money($row['base_salary']) }}</td><td class="text-right">{{ $row['employees'] }}</td>
                    <td>{{ collect($row['components'])->map(fn ($link) => collect($components)->firstWhere('id', $link['pay_component_id'])['name'] ?? null)->filter()->implode(', ') }}</td>
                    <td class="text-right">@can('payroll.manage')<a class="text-indigo-700 hover:underline" href="{{ route('accounting.payroll.grades.index', ['edit' => $row['id']]) }}">Edit</a><form method="POST" action="{{ route('accounting.payroll.grades.destroy', $row['id']) }}" class="inline" onsubmit="return confirm('Delete this grade?')">@csrf @method('DELETE')<button class="ml-2 text-red-700 hover:underline">Delete</button></form>@endcan</td></tr>
            @empty
                <tr><td colspan="6" class="py-4 text-center text-gray-500">No grades yet.</td></tr>
            @endforelse
        </tbody></table></div>
        @can('payroll.manage')
            <form method="POST" action="{{ $editing ? route('accounting.payroll.grades.update', $editing['id']) : route('accounting.payroll.grades.store') }}" class="bg-white shadow rounded-lg p-5 space-y-4">
                @csrf @if($editing) @method('PUT') @endif
                <h3 class="font-semibold text-gray-800">{{ $editing ? 'Edit '.$editing['code'] : 'Add a grade' }}</h3>
                <div class="grid gap-4 md:grid-cols-4">
                    <label class="text-sm"><span class="text-gray-700">Code</span><input name="code" value="{{ old('code', $editing['code'] ?? '') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="text-sm"><span class="text-gray-700">Name</span><input name="name" value="{{ old('name', $editing['name'] ?? '') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="text-sm"><span class="text-gray-700">Monthly basic salary</span><input type="number" step="any" name="base_salary" value="{{ old('base_salary', $editing['base_salary'] ?? '') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                    <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing['is_active'] ?? true))> Active</label>
                </div>
                <div class="space-y-2 text-sm"><p class="font-medium text-gray-800">Allowances and deductions of this grade</p>
                    <input type="hidden" name="components" value="">
                    @forelse ($components as $i => $component)
                        @php($row = $linked->get($component['id']))
                        <div class="flex flex-wrap items-center gap-3" x-data="{ on: {{ $row ? 'true' : 'false' }} }">
                            <label class="flex w-72 items-center gap-2"><input type="checkbox" x-model="on" name="components[{{ $i }}][pay_component_id]" value="{{ $component['id'] }}">{{ $component['name'] }} <span class="text-gray-500">({{ $component['kind'] }})</span></label>
                            <input type="number" step="any" name="components[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="{{ (float) $component['value'] }}" x-show="on" :disabled="!on" class="w-32 border-gray-300 rounded-md shadow-sm text-sm">
                            <span class="text-gray-500" x-show="on">{{ $component['method'] === 'percent_of_basic' ? '% of basic' : ($component['method'] === 'quantity_rate' ? ($component['unit'] ?? 'units').' x '.(float) $component['rate'] : 'per month') }} (blank = {{ (float) $component['value'] }})</span>
                        </div>
                    @empty
                        <p class="text-gray-500">No components defined yet.</p>
                    @endforelse
                </div>
                <div><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">{{ $editing ? 'Save' : 'Add' }}</button>@if($editing) <a href="{{ route('accounting.payroll.grades.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Cancel</a>@endif</div>
            </form>
        @endcan
    </div></div>
</x-accounting::app-layout>
