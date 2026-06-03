<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Bank Book" backRoute="settings.dashboard" :showSearch="true" :showRefresh="true" :createRoute="null" createLabel="" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            <x-accounting::filter-section :action="route('accounting.reports.bank-book')">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <x-accounting::label for="date_from" value="Date From" />
                        <x-accounting::input id="date_from" name="date_from" type="date" class="mt-1 block w-full" :value="$filters['date_from'] ?? ''" />
                    </div>
                    <div>
                        <x-accounting::label for="date_to" value="Date To" />
                        <x-accounting::input id="date_to" name="date_to" type="date" class="mt-1 block w-full" :value="$filters['date_to'] ?? ''" />
                    </div>
                    <div>
                        <x-accounting::label for="status" value="Status" />
                        <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                            <option value="">All</option>
                            <option value="posted" {{ ($filters['status'] ?? '') === 'posted' ? 'selected' : '' }}>Posted</option>
                            <option value="draft" {{ ($filters['status'] ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                        </select>
                    </div>
                </div>
            </x-accounting::filter-section>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 no-print">
                <div class="bg-blue-50 p-4 rounded-lg shadow-sm border border-blue-100">
                    <div class="text-sm text-gray-600">Total Debits (In)</div>
                    <div class="text-2xl font-bold font-mono text-blue-700">{{ number_format($totals['total_debit'] ?? 0, 2) }}</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg shadow-sm border border-green-100">
                    <div class="text-sm text-gray-600">Total Credits (Out)</div>
                    <div class="text-2xl font-bold font-mono text-green-700">{{ number_format($totals['total_credit'] ?? 0, 2) }}</div>
                </div>
                <div class="bg-indigo-50 p-4 rounded-lg shadow-sm border border-indigo-100">
                    <div class="text-sm text-gray-600">Closing Balance</div>
                    <div class="text-2xl font-bold font-mono {{ ($totals['closing_balance'] ?? 0) >= 0 ? 'text-indigo-700' : 'text-red-700' }}">{{ number_format($totals['closing_balance'] ?? 0, 2) }}</div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-4 text-center print-header">
                    <h3 class="text-xl font-bold text-gray-800">Bank Book</h3>
                </div>
                @if($entries->isEmpty())
                <div class="text-center py-8 text-gray-500">No bank book entries found.</div>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm report-table">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-300">
                                <th class="py-2 px-3 text-center font-semibold">Sr.#</th>
                                <th class="py-2 px-3 text-left font-semibold">Date</th>
                                <th class="py-2 px-3 text-left font-semibold">Reference</th>
                                <th class="py-2 px-3 text-left font-semibold">Description</th>
                                <th class="py-2 px-3 text-right font-semibold">Debit</th>
                                <th class="py-2 px-3 text-right font-semibold">Credit</th>
                                <th class="py-2 px-3 text-center font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $i => $row)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-1 px-3 text-center">{{ $entries->firstItem() + $i }}</td>
                                <td class="py-1 px-3">{{ \Carbon\Carbon::parse($row->entry_date)->format('Y-m-d') }}</td>
                                <td class="py-1 px-3 font-mono">{{ $row->reference }}</td>
                                <td class="py-1 px-3">{{ $row->journal_description }}</td>
                                <td class="py-1 px-3 text-right font-mono">{{ $row->debit > 0 ? number_format($row->debit, 2) : '' }}</td>
                                <td class="py-1 px-3 text-right font-mono">{{ $row->credit > 0 ? number_format($row->credit, 2) : '' }}</td>
                                <td class="py-1 px-3 text-center">
                                    <span @class(['px-2 py-0.5 rounded text-xs font-medium', 'bg-green-100 text-green-800' => $row->status === 'posted', 'bg-yellow-100 text-yellow-800' => $row->status === 'draft'])>{{ ucfirst($row->status) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $entries->withQueryString()->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-accounting::app-layout>
