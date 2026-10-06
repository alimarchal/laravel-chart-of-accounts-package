<x-accounting::app-layout title="Budget">
    <x-slot name="header">
        <x-accounting::page-header :title="$budget['name']" :showSearch="false" backRoute="accounting.budgets.index" />
    </x-slot>
    @php($styles = ['ok' => 'bg-emerald-100 text-emerald-800', 'warning' => 'bg-amber-100 text-amber-800', 'over' => 'bg-red-100 text-red-700', 'behind' => 'bg-amber-100 text-amber-800', 'unbudgeted' => 'bg-gray-100 text-gray-600'])
    @php($query = http_build_query(array_filter(['date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'cost_center_id' => $filters['cost_center_id']])))
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">{{ $budget['start_date'] }} → {{ $budget['end_date'] }} · <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ ['draft' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-800', 'closed' => 'bg-gray-100 text-gray-600'][$budget['status']] }}">{{ $budget['status'] }}</span> {{ $budget['notes'] }}</p>
            <div class="flex flex-wrap items-center gap-2">
                @if ($budget['status'] === 'draft')@can('budgets.update')<a href="{{ route('accounting.budgets.edit', $budget['id']) }}" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Edit</a>@endcan @endif
                @if ($budget['status'] === 'draft')@can('budgets.approve')<form method="POST" action="{{ route('accounting.budgets.approve', $budget['id']) }}">@csrf<button class="px-3 py-1.5 bg-blue-950 rounded-md text-xs font-semibold text-white uppercase tracking-widest">Approve</button></form>@endcan @endif
                @if ($budget['status'] === 'approved')@can('budgets.approve')<form method="POST" action="{{ route('accounting.budgets.close', $budget['id']) }}">@csrf<button class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Close</button></form>@endcan @endif
                @if ($budget['status'] !== 'draft')@can('budgets.update')<form method="POST" action="{{ route('accounting.budgets.reopen', $budget['id']) }}">@csrf<button class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Reopen</button></form>@endcan @endif
                @if ($budget['status'] !== 'approved')@can('budgets.delete')<form method="POST" action="{{ route('accounting.budgets.destroy', $budget['id']) }}" onsubmit="return confirm('Delete this budget?')">@csrf @method('DELETE')<button class="px-3 py-1.5 text-red-700 text-xs font-semibold uppercase tracking-widest hover:underline">Delete</button></form>@endcan @endif
                @foreach (['csv', 'xlsx', 'pdf'] as $format)<a href="{{ route('accounting.budgets.export', ['budget' => $budget['id'], 'format' => $format]) }}?{{ $query }}" class="text-xs font-semibold uppercase text-indigo-700 hover:underline">{{ $format }}</a>@endforeach
            </div>
        </div>
        <form method="GET" action="{{ route('accounting.budgets.show', $budget['id']) }}" class="flex flex-wrap items-end gap-3">
            <div><x-accounting::label for="date_from" value="From" /><x-accounting::input id="date_from" name="date_from" type="date" class="mt-1 block" :value="$filters['date_from'] ?? $report['date_from']" /></div>
            <div><x-accounting::label for="date_to" value="To" /><x-accounting::input id="date_to" name="date_to" type="date" class="mt-1 block" :value="$filters['date_to'] ?? $report['date_to']" /></div>
            <div><x-accounting::label for="cost_center_id" value="Cost center" /><select id="cost_center_id" name="cost_center_id" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"><option value="">All</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}" @selected((string) ($filters['cost_center_id'] ?? '') === (string) $center->id)>{{ $center->code }} - {{ $center->name }}</option>@endforeach</select></div>
            <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Apply</button>
        </form>
        <div class="grid gap-3 md:grid-cols-3">
            @foreach ([['Income', 'income'], ['Expenses', 'expense'], ['Net', 'net']] as [$label, $key])
                <div class="bg-white shadow rounded-lg p-3"><div class="text-xs uppercase text-gray-500">{{ $label }}</div><div class="mt-1 text-sm tabular-nums">Budget {{ number_format((float) $report['totals'][$key.'_budget'], 2) }} · Actual {{ number_format((float) $report['totals'][$key.'_actual'], 2) }}</div></div>
            @endforeach
        </div>
        <div class="bg-white shadow rounded-lg overflow-x-auto" x-data="{ open: null }"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Account</th><th class="py-2 px-3 text-right font-medium text-gray-600">Budget</th><th class="py-2 px-3 text-right font-medium text-gray-600">Actual</th><th class="py-2 px-3 text-right font-medium text-gray-600">Variance</th><th class="py-2 px-3 text-right font-medium text-gray-600">Used</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th></tr></thead>
            <tbody>
            @forelse ($report['rows'] as $row)
                <tr class="border-t cursor-pointer" @click="open = open === {{ $row['account_id'] }} ? null : {{ $row['account_id'] }}">
                    <td class="py-2 px-3">{{ $row['account_code'] }} {{ $row['account_name'] }} <span class="ml-2 text-xs text-gray-500">{{ strtolower($row['type']) }}</span></td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['budget'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['actual'], 2) }}</td>
                    <td class="py-2 px-3 text-right tabular-nums {{ (float) $row['variance'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ number_format((float) $row['variance'], 2) }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ $row['used_percent'] === null ? '—' : $row['used_percent'].'%' }}</td>
                    <td class="py-2 px-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$row['status']] }}">{{ $row['status'] }}</span></td>
                </tr>
                <tr class="border-t bg-gray-50" x-show="open === {{ $row['account_id'] }}" x-cloak><td colspan="6" class="px-3 py-2"><div class="flex flex-wrap gap-4 text-xs tabular-nums">@foreach ($row['monthly'] as $month)<div><div class="font-medium">{{ $month['month'] }}</div><div>B {{ number_format((float) $month['budget'], 2) }}</div><div>A {{ number_format((float) $month['actual'], 2) }}</div></div>@endforeach</div></td></tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-gray-500">Nothing budgeted or posted in this range.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <p class="text-xs text-gray-500">Variance is favourable (green) when income is above plan or expenses below it. Click a row for the months.</p>
        @can('budgets.create')
        <form method="POST" action="{{ route('accounting.budgets.copy', $budget['id']) }}" class="bg-white shadow rounded-lg p-3 flex flex-wrap items-end gap-3">@csrf
            <div><x-accounting::label for="copy_name" value="Copy as" /><x-accounting::input id="copy_name" name="name" class="mt-1 block" :value="$budget['name'].' (copy)'" required /></div>
            <div><x-accounting::label for="copy_start" value="Starting" /><x-accounting::input id="copy_start" name="start_date" type="date" class="mt-1 block" /></div>
            <div><x-accounting::label for="copy_uplift" value="Change %" /><input id="copy_uplift" name="uplift_percent" type="number" step="any" class="mt-1 block w-24 border-gray-300 rounded-md shadow-sm text-sm"></div>
            <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Copy budget</button>
        </form>
        @endcan
    </div></div>
</x-accounting::app-layout>
