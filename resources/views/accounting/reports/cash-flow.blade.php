<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Cash Flow" backRoute="settings.dashboard" :showSearch="true" :showRefresh="true" :createRoute="null" createLabel="" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            <x-accounting::filter-section :action="route('settings.reports.cash-flow')">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-accounting::label for="date_from" value="Date From" />
                        <x-accounting::input id="date_from" name="date_from" type="date" class="mt-1 block w-full" :value="$filters['date_from'] ?? ''" />
                    </div>
                    <div>
                        <x-accounting::label for="date_to" value="Date To" />
                        <x-accounting::input id="date_to" name="date_to" type="date" class="mt-1 block w-full" :value="$filters['date_to'] ?? ''" />
                    </div>
                </div>
            </x-accounting::filter-section>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-4 text-center print-header">
                    <h3 class="text-xl font-bold text-gray-800">Cash Flow</h3>
                    @if(($filters['date_from'] ?? null) || ($filters['date_to'] ?? null))
                    <p class="text-gray-600 mt-1 text-sm">
                        {{ ($filters['date_from'] ?? null) ? \Carbon\Carbon::parse($filters['date_from'])->format('M d, Y') : 'Beginning' }}
                        to
                        {{ ($filters['date_to'] ?? null) ? \Carbon\Carbon::parse($filters['date_to'])->format('M d, Y') : 'Present' }}
                    </p>
                    @endif
                </div>

                @if($rows->isEmpty())
                <div class="text-center py-8 text-gray-500">No cash flow entries found.</div>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm report-table">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-300">
                                <th class="py-2 px-3 text-left font-semibold">Date</th>
                                <th class="py-2 px-3 text-left font-semibold">Reference</th>
                                <th class="py-2 px-3 text-left font-semibold">Account</th>
                                <th class="py-2 px-3 text-left font-semibold">Description</th>
                                <th class="py-2 px-3 text-right font-semibold text-green-700">Cash In</th>
                                <th class="py-2 px-3 text-right font-semibold text-red-700">Cash Out</th>
                                <th class="py-2 px-3 text-right font-semibold">Net Cash Flow</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalIn = 0; $totalOut = 0; @endphp
                            @foreach($rows as $row)
                            @php $totalIn += $row->cash_in; $totalOut += $row->cash_out; @endphp
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-1 px-3">{{ \Carbon\Carbon::parse($row->entry_date)->format('Y-m-d') }}</td>
                                <td class="py-1 px-3 font-mono">{{ $row->reference }}</td>
                                <td class="py-1 px-3"><span class="font-mono">{{ $row->account_code }}</span> {{ $row->account_name }}</td>
                                <td class="py-1 px-3">{{ $row->journal_description }}</td>
                                <td class="py-1 px-3 text-right font-mono text-green-700">{{ $row->cash_in > 0 ? number_format($row->cash_in, 2) : '' }}</td>
                                <td class="py-1 px-3 text-right font-mono text-red-700">{{ $row->cash_out > 0 ? number_format($row->cash_out, 2) : '' }}</td>
                                <td class="py-1 px-3 text-right font-mono {{ $row->net_cash_flow >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($row->net_cash_flow, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 border-t-2 border-gray-400 font-bold">
                            <tr>
                                <td colspan="4" class="py-2 px-3 text-right">Totals</td>
                                <td class="py-2 px-3 text-right font-mono text-green-700">{{ number_format($totalIn, 2) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-red-700">{{ number_format($totalOut, 2) }}</td>
                                <td class="py-2 px-3 text-right font-mono {{ ($totalIn - $totalOut) >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($totalIn - $totalOut, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-accounting::app-layout>
