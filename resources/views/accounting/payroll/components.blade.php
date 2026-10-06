<x-accounting::app-layout title="Allowances and deductions">
    <x-slot name="header"><div class="flex items-center justify-between"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Allowances &amp; deductions</h2><a href="{{ route('accounting.payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Payroll</a></div></x-slot>
    <div class="py-6"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session("success"))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session("success") }}</div>@endif
        @if(session("error"))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session("error") }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Earnings are booked to an expense account, deductions to the liability they are owed to.</p>
        <div class="bg-white shadow rounded-lg p-5 overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="py-1">Code</th><th>Name</th><th>Kind</th><th>Amount</th><th>Account</th><th></th></tr></thead><tbody>
            @forelse ($components as $row)
                @php($account = collect($accounts)->firstWhere('id', $row['account_id']))
                <tr class="border-t"><td class="py-2 font-medium">{{ $row['code'] }}</td><td>{{ $row['name'] }} @unless($row['is_active'])<span class="ml-1 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactive</span>@endunless</td><td>{{ $row['kind'] }}{{ $row['kind'] === 'earning' && ! $row['taxable'] ? ' (not taxable)' : '' }}</td><td>{{ (float) $row['value'] }}{{ $row['method'] === 'percent_of_basic' ? '% of basic' : ' per month' }}</td><td>{{ $account['account_code'] ?? '' }} {{ $account['account_name'] ?? '' }}</td>
                    <td class="text-right">@can('payroll.manage')<form method="POST" action="{{ route('accounting.payroll.components.destroy', $row['id']) }}" class="inline" onsubmit="return confirm('Delete this component?')">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Delete</button></form>@endcan</td></tr>
            @empty
                <tr><td colspan="6" class="py-4 text-center text-gray-500">No components yet.</td></tr>
            @endforelse
        </tbody></table></div>
        @can('payroll.manage')
            <form method="POST" action="{{ route('accounting.payroll.components.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4" x-data="{ kind: '{{ old('kind', 'earning') }}' }">
                @csrf <h3 class="font-semibold text-gray-800 md:col-span-4">Add a component</h3>
                <label class="text-sm"><span class="text-gray-700">Code</span><input name="code" value="{{ old('code') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Name</span><input name="name" value="{{ old('name') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700">Kind</span><select name="kind" x-model="kind" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="earning">Earning (allowance)</option><option value="deduction">Deduction</option></select></label>
                <label class="text-sm"><span class="text-gray-700">Method</span><select name="method" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="fixed">Fixed amount per month</option><option value="percent_of_basic">Percent of basic</option></select></label>
                <label class="text-sm"><span class="text-gray-700">Default value</span><input type="number" step="any" name="value" value="{{ old('value') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-sm"><span class="text-gray-700" x-text="kind === 'deduction' ? 'Liability account' : 'Expense account'"></span><select name="account_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Choose…</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}" x-show="kind === '{{ $account['type'] === 'LIABILITY' ? 'deduction' : ($account['type'] === 'EXPENSE' ? 'earning' : 'none') }}'" @selected((string) old('account_id') === (string) $account['id'])>{{ $account['account_code'] }} {{ $account['account_name'] }}</option>@endforeach</select></label>
                <label class="flex items-center gap-2 pt-6 text-sm" x-show="kind === 'earning'"><input type="hidden" name="taxable" value="0"><input type="checkbox" name="taxable" value="1" checked> Counts for income tax</label>
                <div class="pt-6"><button class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Add</button></div>
            </form>
        @endcan
    </div></div>
</x-accounting::app-layout>
