<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Income Statement</h2>
            <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            {{-- Filter Section --}}
            <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-200 no-print">
                <form method="GET" action="{{ route('accounting.reports.income-statement') }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <x-accounting::label for="accounting_period_id" value="Accounting Period" />
                            <select id="accounting_period_id" name="accounting_period_id"
                                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                onchange="this.form.submit()">
                                <option value="">Custom Date Range</option>
                                @foreach($accountingPeriods as $period)
                                <option value="{{ $period->id }}" {{ $periodId == $period->id ? 'selected' : '' }}>
                                    {{ $period->name }} ({{ \Carbon\Carbon::parse($period->start_date)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-accounting::label for="start_date" value="From Date" />
                            <x-accounting::input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="$startDate" />
                        </div>
                        <div>
                            <x-accounting::label for="end_date" value="To Date" />
                            <x-accounting::input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="$endDate" />
                        </div>
                        <div>
                            <x-accounting::label for="filter_account_code" value="Filter by Account Code" />
                            <x-accounting::input id="filter_account_code" name="filter[account_code]" type="text" class="mt-1 block w-full uppercase" :value="request('filter.account_code')" placeholder="e.g., 4000" />
                        </div>
                        <div>
                            <x-accounting::label for="filter_account_name" value="Filter by Account Name" />
                            <x-accounting::input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full" :value="request('filter.account_name')" placeholder="Search by account name" />
                        </div>
                        <div>
                            <x-accounting::label for="filter_account_type" value="Filter by Account Type" />
                            <x-accounting::input id="filter_account_type" name="filter[account_type]" type="text" class="mt-1 block w-full" :value="request('filter.account_type')" placeholder="e.g., Revenue" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <x-accounting::reset-button :href="route('accounting.reports.income-statement')" />
                        <x-accounting::submit-button label="Filter" />
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-bold text-gray-800">Income Statement</h2>
                    <p class="text-lg text-gray-600 mt-2">
                        For the Period: {{ \Carbon\Carbon::parse($startDate)->format('F d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('F d, Y') }}
                    </p>
                </div>

                @php
                $revenue = $groupedAccounts->filter(fn($items, $type) => str_contains(strtolower($type), 'revenue') || str_contains(strtolower($type), 'income'));
                $expenses = $groupedAccounts->filter(fn($items, $type) => str_contains(strtolower($type), 'expense') || str_contains(strtolower($type), 'cost'));

                $totalRevenue = $accounts->filter(fn($item) => str_contains(strtolower($item->account_type), 'revenue') || str_contains(strtolower($item->account_type), 'income'))->sum('balance');
                $totalExpenses = $accounts->filter(fn($item) => str_contains(strtolower($item->account_type), 'expense') || str_contains(strtolower($item->account_type), 'cost'))->sum('balance');
                $netIncome = $totalRevenue - $totalExpenses;
                @endphp

                {{-- Revenue --}}
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4 text-gray-800 border-b-2 border-gray-300 pb-2">REVENUE</h3>
                    @foreach($revenue as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600"><span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name }}</span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2) }}</span>
                        </div>
                        @endforeach
                        <div class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                    <div class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 mt-4 bg-green-50">
                        <span>TOTAL REVENUE</span>
                        <span class="font-mono">{{ number_format($totalRevenue, 2) }}</span>
                    </div>
                </div>

                {{-- Expenses --}}
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4 text-gray-800 border-b-2 border-gray-300 pb-2">EXPENSES</h3>
                    @foreach($expenses as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600"><span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name }}</span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2) }}</span>
                        </div>
                        @endforeach
                        <div class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                    <div class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 mt-4 bg-red-50">
                        <span>TOTAL EXPENSES</span>
                        <span class="font-mono">{{ number_format($totalExpenses, 2) }}</span>
                    </div>
                </div>

                {{-- Net Income --}}
                <div class="flex justify-between py-3 px-2 font-bold text-2xl border-t-4 border-gray-600 mt-8 {{ $netIncome >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    <span>NET {{ $netIncome >= 0 ? 'INCOME' : 'LOSS' }}</span>
                    <span class="font-mono">{{ number_format($netIncome, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</x-accounting::app-layout>
