<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Aged Payables" backRoute="settings.dashboard" :showSearch="false" :showRefresh="true" :createRoute="null" createLabel="" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-4 text-center print-header">
                    <h3 class="text-xl font-bold text-gray-800">Aged Payables</h3>
                    <p class="text-gray-600 mt-1 text-sm">As of {{ now()->format('F d, Y') }}</p>
                </div>
                @if($rows->isEmpty())
                <div class="text-center py-8 text-gray-500">No aged payables found.</div>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm report-table">
                        <thead>
                            <tr class="bg-gray-100 border-b border-gray-300">
                                <th class="py-2 px-3 text-left font-semibold">Account Code</th>
                                <th class="py-2 px-3 text-left font-semibold">Account Name</th>
                                <th class="py-2 px-3 text-right font-semibold">Current (0-30d)</th>
                                <th class="py-2 px-3 text-right font-semibold">31–60 Days</th>
                                <th class="py-2 px-3 text-right font-semibold">61–90 Days</th>
                                <th class="py-2 px-3 text-right font-semibold">Over 90 Days</th>
                                <th class="py-2 px-3 text-right font-semibold">Total Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-1 px-3 font-mono">{{ $row->account_code }}</td>
                                <td class="py-1 px-3">{{ $row->account_name }}</td>
                                <td class="py-1 px-3 text-right font-mono">{{ number_format($row->current_balance, 2) }}</td>
                                <td class="py-1 px-3 text-right font-mono">{{ number_format($row->days_31_60, 2) }}</td>
                                <td class="py-1 px-3 text-right font-mono">{{ number_format($row->days_61_90, 2) }}</td>
                                <td class="py-1 px-3 text-right font-mono text-red-600">{{ number_format($row->over_90, 2) }}</td>
                                <td class="py-1 px-3 text-right font-mono font-bold">{{ number_format($row->balance, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 border-t-2 border-gray-400 font-bold">
                            <tr>
                                <td colspan="2" class="py-2 px-3 text-right">Totals</td>
                                <td class="py-2 px-3 text-right font-mono">{{ number_format($rows->sum('current_balance'), 2) }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ number_format($rows->sum('days_31_60'), 2) }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ number_format($rows->sum('days_61_90'), 2) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-red-600">{{ number_format($rows->sum('over_90'), 2) }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ number_format($rows->sum('balance'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-accounting::app-layout>
