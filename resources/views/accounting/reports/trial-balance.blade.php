<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Trial Balance" backRoute="accounting.dashboard" :showSearch="true" :showRefresh="true" :createRoute="null" createLabel="" />
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4"><x-accounting::export-buttons report="trial-balance" /></div>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            <x-accounting::filter-section :action="route('accounting.reports.trial-balance')">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <x-accounting::label for="accounting_period_id" value="Accounting Period" />
                        <select id="accounting_period_id" name="accounting_period_id"
                            class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            onchange="this.form.submit()">
                            <option value="">Custom Date</option>
                            @foreach($accountingPeriods as $period)
                                <option value="{{ $period->id }}" {{ $periodId == $period->id ? 'selected' : '' }}>
                                    {{ $period->name }} (As of {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-accounting::label for="as_of_date" value="As of Date" />
                        <x-accounting::input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
                    </div>
                    <div>
                        <x-accounting::label for="search" value="Search" />
                        <x-accounting::input id="search" name="search" type="search" class="mt-1 block w-full" :value="$filters['search'] ?? ''" placeholder="Account code or name" />
                    </div>
                    <div>
                        <x-accounting::label for="account_type" value="Account type" />
                        <select id="account_type" name="account_type" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                            <option value="">All</option>
                            @foreach ($rows->pluck('account_type')->unique()->sort() as $type)
                                <option value="{{ $type }}" @selected(($filters['account_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="hide_zero" value="1" @checked(filter_var($filters['hide_zero'] ?? false, FILTER_VALIDATE_BOOLEAN))> Hide accounts without activity</label>
                    </div>
                </div>
            </x-accounting::filter-section>

            {{-- Summary --}}
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-4 text-center">
                    <h3 class="text-xl font-bold text-gray-800">Trial Balance</h3>
                    <p class="text-gray-600 mt-1">
                        As of {{ \Carbon\Carbon::parse($asOfDate)->format('F d, Y') }}
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-sm text-gray-600">Total debit balances</div>
                        <div class="text-2xl font-bold font-mono text-blue-700">
                            {{ number_format((float) $trialBalance->total_debit_balances, 2) }}
                        </div>
                        <div class="text-xs text-gray-500">Movements {{ number_format((float) $trialBalance->total_debits, 2) }}</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-sm text-gray-600">Total credit balances</div>
                        <div class="text-2xl font-bold font-mono text-green-700">
                            {{ number_format((float) $trialBalance->total_credit_balances, 2) }}
                        </div>
                        <div class="text-xs text-gray-500">Movements {{ number_format((float) $trialBalance->total_credits, 2) }}</div>
                    </div>
                    <div class="p-4 rounded-lg {{ abs($trialBalance->difference) < 0.01 ? 'bg-emerald-50' : 'bg-red-50' }}">
                        <div class="text-sm text-gray-600">Difference</div>
                        <div class="text-2xl font-bold font-mono {{ abs($trialBalance->difference) < 0.01 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format((float) $trialBalance->difference, 2) }}
                        </div>
                        @if(abs($trialBalance->difference) < 0.01)
                            <div class="text-xs text-emerald-600 mt-1">✓ Balanced</div>
                        @else
                            <div class="text-xs text-red-600 mt-1">⚠️ Out of Balance</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Account</th><th class="py-2 px-3 text-left font-medium text-gray-600">Type</th><th class="py-2 px-3 text-right font-medium text-gray-600">Movements Dr</th><th class="py-2 px-3 text-right font-medium text-gray-600">Movements Cr</th><th class="py-2 px-3 text-right font-medium text-gray-600">Debit balance</th><th class="py-2 px-3 text-right font-medium text-gray-600">Credit balance</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t"><td class="py-2 px-3">{{ $row->account_code }}</td><td class="py-2 px-3">{{ $row->account_name }}</td><td class="py-2 px-3">{{ $row->account_type }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row->total_debits, 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row->total_credits, 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row->debit_balance, 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row->credit_balance, 2) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-gray-500">No accounts match.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</x-accounting::app-layout>
