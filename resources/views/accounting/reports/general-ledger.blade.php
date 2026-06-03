<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header
            title="General Ledger"
            backRoute="settings.dashboard"
            :showSearch="true"
            :showRefresh="true"
            :createRoute="null"
            createLabel=""
        />
    </x-slot>

    @push('styles')
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

    <x-accounting::filter-section :action="route('settings.reports.general-ledger')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            <div class="xl:col-span-2">
                <x-accounting::label for="accounting_period_id" value="Accounting Period" />
                <select id="accounting_period_id" name="accounting_period_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2"
                    onchange="this.form.submit()">
                    <option value="">All Time (Custom Dates)</option>
                    @foreach($accountingPeriods as $period)
                        <option value="{{ $period->id }}" {{ $periodId == $period->id ? 'selected' : '' }}>
                            {{ $period->name }} ({{ \Carbon\Carbon::parse($period->start_date)->format('M d, Y') }} -
                            {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_entry_date_from" value="Date From" />
                <x-accounting::input id="filter_entry_date_from" name="filter[entry_date_from]" type="date"
                    class="mt-1 block w-full" :value="$entryDateFrom" />
            </div>

            <div>
                <x-accounting::label for="filter_entry_date_to" value="Date To" />
                <x-accounting::input id="filter_entry_date_to" name="filter[entry_date_to]" type="date"
                    class="mt-1 block w-full" :value="$entryDateTo" />
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
                <x-accounting::label for="filter_reference" value="Reference" />
                <x-accounting::input id="filter_reference" name="filter[reference]" type="text"
                    class="mt-1 block w-full" :value="request('filter.reference')" placeholder="Reference contains..." />
            </div>

            <div>
                <x-accounting::label for="filter_cost_center_code" value="Cost Center" />
                <select id="filter_cost_center_code" name="filter[cost_center_code]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Cost Centers</option>
                    @foreach($costCenters as $cc)
                        <option value="{{ $cc->code }}" {{ request('filter.cost_center_code') === $cc->code ? 'selected' : '' }}>
                            {{ $cc->code }} - {{ $cc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.status') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-accounting::label for="filter_debit_min" value="Debit (Min)" />
                <x-accounting::input id="filter_debit_min" name="filter[debit_min]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.debit_min')" placeholder="0.00" />
            </div>

            <div>
                <x-accounting::label for="filter_debit_max" value="Debit (Max)" />
                <x-accounting::input id="filter_debit_max" name="filter[debit_max]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.debit_max')" placeholder="Any" />
            </div>

            <div>
                <x-accounting::label for="filter_credit_min" value="Credit (Min)" />
                <x-accounting::input id="filter_credit_min" name="filter[credit_min]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.credit_min')" placeholder="0.00" />
            </div>

            <div>
                <x-accounting::label for="filter_credit_max" value="Credit (Max)" />
                <x-accounting::input id="filter_credit_max" name="filter[credit_max]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.credit_max')" placeholder="Any" />
            </div>

            <div>
                <x-accounting::label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="entry_date" {{ request('sort', 'entry_date') == 'entry_date' ? 'selected' : '' }}>Date (Oldest)</option>
                    <option value="-entry_date" {{ request('sort') == '-entry_date' ? 'selected' : '' }}>Date (Newest)</option>
                    <option value="account_code" {{ request('sort') == 'account_code' ? 'selected' : '' }}>Account Code (A-Z)</option>
                    <option value="-account_code" {{ request('sort') == '-account_code' ? 'selected' : '' }}>Account Code (Z-A)</option>
                    <option value="-debit" {{ request('sort') == '-debit' ? 'selected' : '' }}>Debit (High-Low)</option>
                    <option value="-credit" {{ request('sort') == '-credit' ? 'selected' : '' }}>Credit (High-Low)</option>
                    <option value="status" {{ request('sort') == 'status' ? 'selected' : '' }}>Status</option>
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
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Entries</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totals->total_entries ?? 0) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Debits</p>
                <p class="text-2xl font-bold font-mono text-green-700">{{ number_format($totals->total_debits ?? 0, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-600">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Credits</p>
                <p class="text-2xl font-bold font-mono text-red-700">{{ number_format($totals->total_credits ?? 0, 2) }}</p>
            </div>
            @php $netDifference = ($totals->total_debits ?? 0) - ($totals->total_credits ?? 0); @endphp
            <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ abs($netDifference) < 0.01 ? 'border-emerald-600' : 'border-yellow-500' }}">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Net Difference</p>
                <p class="text-2xl font-bold font-mono {{ abs($netDifference) < 0.01 ? 'text-emerald-700' : 'text-yellow-600' }}">
                    {{ number_format($netDifference, 2) }}
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <x-accounting::status-message class="mb-4" />
        <div class="bg-white overflow-hidden pt-4 pb-4 pl-0 pr-0 shadow-xl sm:rounded-lg mb-4 print:shadow-none">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Accounting General Ledger<br>
                    @if($entryDateFrom && $entryDateTo)
                        <span class="text-sm font-semibold">
                            Period: {{ \Carbon\Carbon::parse($entryDateFrom)->format('d-M-Y') }} to
                            {{ \Carbon\Carbon::parse($entryDateTo)->format('d-M-Y') }}
                        </span>
                    @elseif($entryDateTo)
                        <span class="text-sm font-semibold">As of: {{ \Carbon\Carbon::parse($entryDateTo)->format('d-M-Y') }}</span>
                    @elseif($entryDateFrom)
                        <span class="text-sm font-semibold">From: {{ \Carbon\Carbon::parse($entryDateFrom)->format('d-M-Y') }}</span>
                    @else
                        <span class="text-sm font-semibold">All Time</span>
                    @endif
                    <br>
                    <span class="print-only text-xs font-normal">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="text-center">Sr.#</th>
                            <th class="text-center">Date</th>
                            <th class="text-left">Code</th>
                            <th class="text-left">Account Name</th>
                            <th class="text-left">Description / LD / Reference</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $index => $entry)
                            <tr>
                                <td class="text-center text-xs">{{ $entries->firstItem() + $index }}</td>
                                <td class="text-center whitespace-nowrap text-xs">
                                    {{ $entry->entry_date ? \Carbon\Carbon::parse($entry->entry_date)->format('d-M-Y') : '-' }}
                                </td>
                                <td class="text-left font-mono whitespace-nowrap text-xs">{{ $entry->account_code }}</td>
                                <td class="text-left whitespace-nowrap text-xs">{{ $entry->account_name }}</td>
                                <td class="text-left text-xs">
                                    {{ $entry->journal_description ?? '-' }} ::
                                    {{ $entry->line_description ?? '-' }} **
                                    Ref: {{ $entry->reference ?? '-' }} -
                                    CC: {{ $entry->cost_center_code ?? '-' }}
                                </td>
                                <td class="text-right font-mono whitespace-nowrap text-xs">
                                    {{ (float) $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}
                                </td>
                                <td class="text-right font-mono whitespace-nowrap text-xs">
                                    {{ (float) $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}
                                </td>
                                <td class="text-center whitespace-nowrap text-xs">
                                    {{ ucfirst($entry->status ?? 'draft') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    No general ledger entries found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($entries->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td class="text-right" colspan="5">Page Total ({{ $entries->count() }} entries):</td>
                                <td class="text-right font-mono">{{ number_format($entries->sum('debit'), 2) }}</td>
                                <td class="text-right font-mono">{{ number_format($entries->sum('credit'), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

                @if($entries->hasPages())
                    <div class="mt-4 no-print">
                        {{ $entries->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-accounting::app-layout>
