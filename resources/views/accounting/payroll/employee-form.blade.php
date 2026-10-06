<x-accounting::app-layout title="Employee">
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $employee ? 'Edit '.$employee['code'] : 'New employee' }}</h2></x-slot>
    @php($v = fn ($name, $default = '') => old($name, $employee[$name] ?? $default))
    @php($linked = collect($employee['components'] ?? [])->keyBy('pay_component_id'))
    <div class="py-6"><div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <form method="POST" action="{{ $employee ? route('accounting.payroll.employees.update', $employee['id']) : route('accounting.payroll.employees.store') }}" class="space-y-4">
            @csrf @if($employee) @method('PUT') @endif
            <div class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3">
                @foreach ([['code', 'Code', 'text'], ['name', 'Name', 'text'], ['national_id', 'National ID', 'text'], ['designation', 'Designation', 'text'], ['join_date', 'Joined', 'date'], ['leave_date', 'Left (if so)', 'date'], ['base_salary', 'Monthly basic salary', 'number'], ['bank_name', 'Bank', 'text'], ['bank_account', 'Bank account', 'text']] as [$name, $label, $type])
                    <label class="text-sm"><span class="text-gray-700">{{ $label }}</span><input type="{{ $type }}" step="any" name="{{ $name }}" value="{{ $v($name, $name === 'join_date' ? $today : '') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                @endforeach
                <label class="text-sm"><span class="text-gray-700">Cost center</span><select name="cost_center_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">None</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}" @selected((string) $v('cost_center_id') === (string) $center->id)>{{ $center->code }} {{ $center->name }}</option>@endforeach</select></label>
                <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="withhold_tax" value="0"><input type="checkbox" name="withhold_tax" value="1" @checked($v('withhold_tax', false))> Withhold income tax</label>
                <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($v('is_active', true))> Active</label>
            </div>
            <div class="bg-white shadow rounded-lg p-5 space-y-2 text-sm"><h3 class="font-semibold text-gray-800">Allowances and deductions</h3>
                <input type="hidden" name="components" value="">
                @forelse ($components as $i => $component)
                    @php($row = $linked->get($component['id']))
                    <div class="flex flex-wrap items-center gap-3" x-data="{ on: {{ $row ? 'true' : 'false' }} }">
                        <label class="flex w-72 items-center gap-2"><input type="checkbox" x-model="on" name="components[{{ $i }}][pay_component_id]" value="{{ $component['id'] }}">{{ $component['name'] }} <span class="text-gray-500">({{ $component['kind'] }})</span></label>
                        <input type="number" step="any" name="components[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="{{ (float) $component['value'] }}" x-show="on" :disabled="!on" class="w-32 border-gray-300 rounded-md shadow-sm text-sm">
                        <span class="text-gray-500" x-show="on">{{ $component['method'] === 'percent_of_basic' ? '% of basic' : 'per month' }} (blank = {{ (float) $component['value'] }})</span>
                    </div>
                @empty
                    <p class="text-gray-500">No components defined yet.</p>
                @endforelse
            </div>
            <div><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Save</button> <a href="{{ route('accounting.payroll.employees.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Cancel</a></div>
        </form>
    </div></div>
</x-accounting::app-layout>
