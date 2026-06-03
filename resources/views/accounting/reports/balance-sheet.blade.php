<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header
            title="Balance Sheet"
            backRoute="settings.dashboard"
            :showSearch="true"
            :showRefresh="true"
            :createRoute="null"
            createLabel=""
        />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            <x-accounting::filter-section :action="route('settings.reports.balance-sheet')">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                        <x-accounting::label for="filter_account_code" value="Filter by Account Code" />
                        <x-accounting::input id="filter_account_code" name="filter[account_code]" type="text" class="mt-1 block w-full uppercase" :value="request('filter.account_code')" placeholder="e.g., 1000" />
                    </div>
                    <div>
                        <x-accounting::label for="filter_account_name" value="Filter by Account Name" />
                        <x-accounting::input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full" :value="request('filter.account_name')" placeholder="Search by account name" />
                    </div>
                    <div>
                        <x-accounting::label for="filter_account_type" value="Filter by Account Type" />
                        <x-accounting::input id="filter_account_type" name="filter[account_type]" type="text" class="mt-1 block w-full" :value="request('filter.account_type')" placeholder="e.g., Asset" />
                    </div>
                </div>
            </x-accounting::filter-section>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-bold text-gray-800">Balance Sheet</h2>
                    <p class="text-lg text-gray-600 mt-2">
                        As of {{ \Carbon\Carbon::parse($asOfDate)->format('F d, Y') }}
                    </p>
                </div>

                @php
                $assets = $groupedAccounts->filter(fn($items, $type) => str_contains(strtolower($type), 'asset'));
                $liabilities = $groupedAccounts->filter(fn($items, $type) => str_contains(strtolower($type), 'liability'));
                $equity = $groupedAccounts->filter(fn($items, $type) => str_contains(strtolower($type), 'equity'));

                $totalAssets = $accounts->filter(fn($item) => str_contains(strtolower($item->account_type), 'asset'))->sum('balance');
                $totalLiabilities = $accounts->filter(fn($item) => str_contains(strtolower($item->account_type), 'liability'))->sum('balance');
                $totalEquity = $accounts->filter(fn($item) => str_contains(strtolower($item->account_type), 'equity'))->sum('balance');
                @endphp

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Assets --}}
                    <div>
                        <h3 class="text-xl font-bold mb-4 text-gray-800 border-b-2 border-gray-300 pb-2">ASSETS</h3>
                        @foreach($assets as $accountType => $items)
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
                        <div class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 mt-4 bg-blue-50">
                            <span>TOTAL ASSETS</span>
                            <span class="font-mono">{{ number_format($totalAssets, 2) }}</span>
                        </div>
                    </div>

                    {{-- Liabilities & Equity --}}
                    <div>
                        <h3 class="text-xl font-bold mb-4 text-gray-800 border-b-2 border-gray-300 pb-2">LIABILITIES & EQUITY</h3>
                        @foreach($liabilities as $accountType => $items)
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
                        @foreach($equity as $accountType => $items)
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
                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-700 mb-2">Current Period</h4>
                            <div class="flex justify-between py-1 px-2 text-sm">
                                <span class="text-gray-600"><span class="font-mono">NET</span> - Net Income (Current Period)</span>
                                <span class="font-mono font-semibold {{ $netIncome >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format((float) $netIncome, 2) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 mt-4 bg-blue-50">
                            <span>TOTAL LIABILITIES & EQUITY</span>
                            <span class="font-mono">{{ number_format($totalLiabilities + $totalEquity + $netIncome, 2) }}</span>
                        </div>
                    </div>
                </div>

                @php $difference = abs($totalAssets - ($totalLiabilities + $totalEquity + $netIncome)); @endphp
                @if($difference > 0.01)
                <div class="mt-6 p-4 bg-red-100 border border-red-400 rounded">
                    <p class="text-red-700 font-semibold">⚠️ Balance Sheet does not balance! Difference: {{ number_format($difference, 2) }}</p>
                </div>
                @else
                <div class="mt-6 p-4 bg-green-100 border border-green-400 rounded">
                    <p class="text-green-700 font-semibold text-center">✓ Balance Sheet is balanced</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-accounting::app-layout>
