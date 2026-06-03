<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header
            title="Account Balances"
            backRoute="accounting.dashboard"
            :showSearch="true"
            :showRefresh="true"
            :createRoute="null"
            createLabel=""
        />
    </x-slot>

    @push('header')
        <style>
            .report-table { width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 14px; }
            .report-table th, .report-table td { border: 1px solid #000; padding: 5px 8px; }
            .report-table th { background-color: #1f2937; color: #fff; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
            .report-table tbody tr:nth-child(even) { background-color: #f9fafb; }
            .report-table tbody tr:hover { background-color: #e5e7eb; }
            .report-table tfoot td { background-color: #1f2937; color: #fff; font-weight: bold; }
            .print-only { display: none; }
            @media print {
                @page { margin: 5mm; }
                body { margin: 0; padding: 0; }
                .no-print { display: none !important; }
                .print-only { display: block !important; }
                .max-w-8xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                .bg-white { margin: 0 !important; padding: 10px !important; box-shadow: none !important; }
                .overflow-x-auto { overflow: visible !important; }
                .report-table { font-size: 10px; }
                .report-table th { background-color: #e5e7eb !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .report-table tfoot td { background-color: #e5e7eb !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .report-table tbody tr:nth-child(even) { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .report-table tbody tr:hover { background-color: transparent; }
            }
        </style>
    @endpush

    <x-accounting::filter-section :action="route('accounting.reports.account-balances')" class="no-print" :maxWidth="'max-w-8xl'">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4">
            <div class="xl:col-span-2">
                <x-accounting::label for="accounting_period_id" value="Accounting Period" />
                <select id="accounting_period_id" name="accounting_period_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                    onchange="this.form.submit()">
                    <option value="">All Time (Custom Date)</option>
                    @foreach($accountingPeriods as $period)
                        <option value="{{ $period->id }}" {{ $periodId == $period->id ? 'selected' : '' }}>
                            {{ $period->name }} ({{ \Carbon\Carbon::parse($period->start_date)->format('M d, Y') }} -
                            {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="as_of_date" value="As of Date" />
                <x-accounting::input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
            </div>

            <div>
                <x-accounting::label for="filter_account_code" value="Account Code" />
                <select id="filter_account_code" name="filter[account_code]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->account_code }}" {{ request('filter.account_code') === $account->account_code ? 'selected' : '' }}>
                            {{ $account->account_code }} - {{ $account->account_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_account_name" value="Account Name" />
                <select id="filter_account_name" name="filter[account_name]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->account_name }}" {{ request('filter.account_name') === $account->account_name ? 'selected' : '' }}>
                            {{ $account->account_name }} ({{ $account->account_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_account_type" value="Account Type" />
                <select id="filter_account_type" name="filter[account_type]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach($accountTypes as $type)
                        <option value="{{ $type }}" {{ request('filter.account_type') === $type ? 'selected' : '' }}>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_normal_balance" value="Normal Balance" />
                <select id="filter_normal_balance" name="filter[normal_balance]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="debit" {{ request('filter.normal_balance') === 'debit' ? 'selected' : '' }}>Debit</option>
                    <option value="credit" {{ request('filter.normal_balance') === 'credit' ? 'selected' : '' }}>Credit</option>
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_is_active" value="Active Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="true" {{ request('filter.is_active') === 'true' ? 'selected' : '' }}>Active</option>
                    <option value="false" {{ request('filter.is_active') === 'false' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_balance_min" value="Balance (Min)" />
                <x-accounting::input id="filter_balance_min" name="filter[balance_min]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_min')" placeholder="0.00" />
            </div>

            <div>
                <x-accounting::label for="filter_balance_max" value="Balance (Max)" />
                <x-accounting::input id="filter_balance_max" name="filter[balance_max]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_max')" placeholder="Any" />
            </div>

            <div>
                <x-accounting::label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="account_code" {{ request('sort') == 'account_code' || !request('sort') ? 'selected' : '' }}>Account Code (A-Z)</option>
                    <option value="-account_code" {{ request('sort') == '-account_code' ? 'selected' : '' }}>Account Code (Z-A)</option>
                    <option value="account_name" {{ request('sort') == 'account_name' ? 'selected' : '' }}>Account Name (A-Z)</option>
                    <option value="-account_name" {{ request('sort') == '-account_name' ? 'selected' : '' }}>Account Name (Z-A)</option>
                    <option value="-balance" {{ request('sort') == '-balance' ? 'selected' : '' }}>Balance (High-Low)</option>
                    <option value="balance" {{ request('sort') == 'balance' ? 'selected' : '' }}>Balance (Low-High)</option>
                    <option value="account_type" {{ request('sort') == 'account_type' ? 'selected' : '' }}>Account Type</option>
                </select>
            </div>

            <div>
                <x-accounting::label for="per_page" value="Per Page" />
                <select id="per_page" name="per_page"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', 100) == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page') == 250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-accounting::filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Accounts</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($balances->total()) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Debits</p>
                <p class="text-2xl font-bold tabular-nums text-green-700">
                    {{ number_format($balances->sum('total_debits'), 2) }}
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Credits</p>
                <p class="text-2xl font-bold tabular-nums text-red-700">
                    {{ number_format($balances->sum('total_credits'), 2) }}
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Net Balance</p>
                <p class="text-2xl font-bold tabular-nums text-gray-900">
                    {{ number_format($balances->sum('balance'), 2) }}
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Account Balances<br>
                    <span class="text-sm font-semibold">As of: {{ \Carbon\Carbon::parse($asOfDate)->format('d-M-Y') }}</span>
                    <br>
                    <span class="print-only text-xs font-normal">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="text-center">Sr.#</th>
                            <th class="text-center">Code</th>
                            <th class="text-left">Account Name</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Group</th>
                            <th class="text-right">Total Debits</th>
                            <th class="text-right">Total Credits</th>
                            <th class="text-right">Balance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($balances as $index => $balance)
                            <tr>
                                <td class="text-center">{{ $balances->firstItem() + $index }}</td>
                                <td class="text-center tabular-nums whitespace-nowrap">{{ $balance->account_code }}</td>
                                <td class="text-left whitespace-nowrap">{{ $balance->account_name }}</td>
                                <td class="text-center whitespace-nowrap">{{ $balance->account_type }}</td>
                                <td class="text-center whitespace-nowrap">
                                    {{ $balance->report_group === 'BalanceSheet' ? 'Balance Sheet' : 'Income Statement' }}
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ (float) $balance->total_debits > 0 ? number_format($balance->total_debits, 2) : '0.00' }}
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ (float) $balance->total_credits > 0 ? number_format($balance->total_credits, 2) : '0.00' }}
                                </td>
                                <td class="text-right tabular-nums font-bold {{ $balance->balance < 0 ? 'text-red-600' : '' }}">
                                    {{ number_format((float) $balance->balance, 2) }}
                                </td>
                                <td class="text-center whitespace-nowrap">{{ $balance->is_active ? 'Active' : 'Inactive' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">No account balances found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($balances->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td class="text-right" colspan="5">Grand Total ({{ $balances->total() }} accounts):</td>
                                <td class="text-right tabular-nums">{{ number_format($balances->sum('total_debits'), 2) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($balances->sum('total_credits'), 2) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($balances->sum('balance'), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

                @if($balances->hasPages())
                    <div class="mt-4 no-print">
                        {{ $balances->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-accounting::app-layout>
        <style>
            .report-table { width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 14px; }
            .report-table th, .report-table td { border: 1px solid #000; padding: 5px 8px; }
            .report-table th { background-color: #1f2937; color: #fff; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
            .report-table tbody tr:nth-child(even) { background-color: #f9fafb; }
            .report-table tbody tr:hover { background-color: #e5e7eb; }
            .report-table tfoot td { background-color: #1f2937; color: #fff; font-weight: bold; }
            .print-only { display: none; }
            @media print {
                @page { margin: 5mm; }
                body { margin: 0; padding: 0; }
                .no-print { display: none !important; }
                .print-only { display: block !important; }
                .max-w-7xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                .bg-white { margin: 0 !important; padding: 10px !important; box-shadow: none !important; }
                .overflow-x-auto { overflow: visible !important; }
                .report-table { font-size: 9px; }
                .report-table th { background-color: #e5e7eb !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .report-table tfoot td { background-color: #e5e7eb !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    @endpush

    <x-accounting::filter-section :action="route('accounting.reports.account-balances')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4">
            <div class="xl:col-span-2">
                <x-accounting::label for="accounting_period_id" value="Accounting Period" />
                <select id="accounting_period_id" name="accounting_period_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2"
                    onchange="this.form.submit()">
                    <option value="">All Time (Custom Date)</option>
                    @foreach($accountingPeriods as $period)
                        <option value="{{ $period->id }}" {{ $periodId == $period->id ? 'selected' : '' }}>
                            {{ $period->name }} ({{ \Carbon\Carbon::parse($period->start_date)->format('M d, Y') }} -
                            {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="as_of_date" value="As of Date" />
                <x-accounting::input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
            </div>

            <div>
                <x-accounting::label for="filter_account_code" value="Account Code" />
                <select id="filter_account_code" name="filter[account_code]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->account_code }}" {{ request('filter.account_code') === $account->account_code ? 'selected' : '' }}>
                            {{ $account->account_code }} - {{ $account->account_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_account_name" value="Account Name" />
                <select id="filter_account_name" name="filter[account_name]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->account_name }}" {{ request('filter.account_name') === $account->account_name ? 'selected' : '' }}>
                            {{ $account->account_name }} ({{ $account->account_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_account_type" value="Account Type" />
                <select id="filter_account_type" name="filter[account_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Types</option>
                    @foreach($accountTypes as $type)
                        <option value="{{ $type }}" {{ request('filter.account_type') === $type ? 'selected' : '' }}>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_normal_balance" value="Normal Balance" />
                <select id="filter_normal_balance" name="filter[normal_balance]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All</option>
                    <option value="debit" {{ request('filter.normal_balance') === 'debit' ? 'selected' : '' }}>Debit</option>
                    <option value="credit" {{ request('filter.normal_balance') === 'credit' ? 'selected' : '' }}>Credit</option>
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_is_active" value="Active Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All</option>
                    <option value="true" {{ request('filter.is_active') === 'true' ? 'selected' : '' }}>Active</option>
                    <option value="false" {{ request('filter.is_active') === 'false' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div>
                <x-accounting::label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="account_code" {{ request('sort', 'account_code') == 'account_code' ? 'selected' : '' }}>Account Code (A-Z)</option>
                    <option value="-account_code" {{ request('sort') == '-account_code' ? 'selected' : '' }}>Account Code (Z-A)</option>
                    <option value="account_name" {{ request('sort') == 'account_name' ? 'selected' : '' }}>Account Name (A-Z)</option>
                    <option value="-account_name" {{ request('sort') == '-account_name' ? 'selected' : '' }}>Account Name (Z-A)</option>
                    <option value="-balance" {{ request('sort') == '-balance' ? 'selected' : '' }}>Balance (High-Low)</option>
                    <option value="balance" {{ request('sort') == 'balance' ? 'selected' : '' }}>Balance (Low-High)</option>
                    <option value="account_type" {{ request('sort') == 'account_type' ? 'selected' : '' }}>Account Type</option>
                </select>
            </div>

            <div>
                <x-accounting::label for="per_page" value="Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', 100) == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page') == 250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-accounting::filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Accounts</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totals->total_accounts ?? 0) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Debits</p>
                <p class="text-2xl font-bold tabular-nums text-green-700">
                    {{ number_format($totals->total_debits ?? 0, 2) }}
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Credits</p>
                <p class="text-2xl font-bold tabular-nums text-red-700">
                    {{ number_format($totals->total_credits ?? 0, 2) }}
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Net Balance</p>
                <p class="text-2xl font-bold tabular-nums text-gray-900">
                    {{ number_format($totals->net_balance ?? 0, 2) }}
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <x-accounting::status-message class="mb-4 mt-4 shadow-md" />
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Account Balances<br>
                    <span class="text-sm font-semibold">As of:
                        {{ \Carbon\Carbon::parse($asOfDate)->format('d-M-Y') }}</span>
                    <br>
                    <span class="print-only text-xs font-normal">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="text-center">Sr.#</th>
                            <th class="text-center">Code</th>
                            <th class="text-left">Account Name</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Group</th>
                            <th class="text-right">Total Debits</th>
                            <th class="text-right">Total Credits</th>
                            <th class="text-right">Balance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($balances as $index => $balance)
                            <tr>
                                <td class="text-center">{{ $balances->firstItem() + $index }}</td>
                                <td class="text-center tabular-nums whitespace-nowrap">{{ $balance->account_code }}</td>
                                <td class="text-left whitespace-nowrap">{{ $balance->account_name }}</td>
                                <td class="text-center whitespace-nowrap">{{ $balance->account_type }}</td>
                                <td class="text-center whitespace-nowrap">
                                    {{ $balance->report_group === 'BalanceSheet' ? 'Balance Sheet' : 'Income Statement' }}
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ (float) $balance->total_debits > 0 ? number_format($balance->total_debits, 2) : '0.00' }}
                                </td>
                                <td class="text-right tabular-nums">
                                    {{ (float) $balance->total_credits > 0 ? number_format($balance->total_credits, 2) : '0.00' }}
                                </td>
                                <td class="text-right tabular-nums font-bold {{ $balance->balance < 0 ? 'text-red-600' : '' }}">
                                    {{ number_format((float) $balance->balance, 2) }}
                                </td>
                                <td class="text-center whitespace-nowrap">{{ $balance->is_active ? 'Active' : 'Inactive' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    No account balances found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($balances->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td class="text-right" colspan="5">Grand Total ({{ $balances->total() }} accounts):</td>
                                <td class="text-right tabular-nums">{{ number_format($balances->sum('total_debits'), 2) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($balances->sum('total_credits'), 2) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($balances->sum('balance'), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

                @if($balances->hasPages())
                    <div class="mt-4 no-print">
                        {{ $balances->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-accounting::app-layout>
