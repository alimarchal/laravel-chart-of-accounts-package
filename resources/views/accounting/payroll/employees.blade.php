<x-accounting::app-layout title="Employees">
    <x-slot name="header"><x-accounting::page-header title="Employees" :showSearch="false" :showRefresh="false" :showPrint="false" backRoute="accounting.payroll.index" :createRoute="route('accounting.payroll.employees.create')" createLabel="New employee" createPermission="payroll.manage" /><x-accounting::payroll-nav /></x-slot>
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <x-accounting::list-filters :action="route('accounting.payroll.employees.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'Code, name, ID, designation or bank'],
            ['name' => 'active', 'label' => 'Status', 'type' => 'select', 'options' => ['1' => 'Active', '0' => 'Left']],
            ['name' => 'withhold_tax', 'label' => 'Tax withheld', 'type' => 'select', 'options' => ['1' => 'Yes', '0' => 'No']],
            ['name' => 'cost_center_id', 'label' => 'Cost center', 'type' => 'select', 'options' => collect($costCenters)->mapWithKeys(fn ($row) => [$row->id => $row->code.' '.$row->name])->all()],
            ['name' => 'joined_from', 'label' => 'Joined from', 'type' => 'date'],
            ['name' => 'joined_to', 'label' => 'Joined to', 'type' => 'date'],
            ['name' => 'min_salary', 'label' => 'Salary from', 'type' => 'number'],
            ['name' => 'max_salary', 'label' => 'Salary up to', 'type' => 'number'],
        ]" />
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Name</th><th class="py-2 px-3 text-left font-medium text-gray-600">Designation</th><th class="py-2 px-3 text-left font-medium text-gray-600">Joined</th><th class="py-2 px-3 text-left font-medium text-gray-600">Left</th><th class="py-2 px-3 text-right font-medium text-gray-600">Monthly salary</th><th class="py-2 px-3 text-left font-medium text-gray-600">Tax</th><th></th></tr></thead>
            <tbody>
            @forelse ($employees as $row)
                <tr class="border-t"><td class="py-2 px-3 font-medium">{{ $row['code'] }}</td><td class="py-2 px-3">{{ $row['name'] }} @unless($row['is_active'])<span class="ml-1 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactive</span>@endunless</td><td class="py-2 px-3">{{ $row['designation'] }}</td><td class="py-2 px-3">{{ $row['join_date'] }}</td><td class="py-2 px-3">{{ $row['leave_date'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $fmt($row['base_salary']) }}</td><td class="py-2 px-3">{{ $row['withhold_tax'] ? 'withheld' : '' }}</td>
                    <td class="py-2 px-3 text-right">@can('payroll.manage')<a class="text-indigo-700 hover:underline" href="{{ route('accounting.payroll.employees.edit', $row['id']) }}">Edit</a><form method="POST" action="{{ route('accounting.payroll.employees.destroy', $row['id']) }}" class="inline" onsubmit="return confirm('Delete this employee?')">@csrf @method('DELETE')<button class="ml-2 text-red-700 hover:underline">Delete</button></form>@endcan</td></tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-gray-500">No employees yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
