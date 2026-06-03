<div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200 rounded-md">
            <thead class="bg-gray-50"><tr>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Account Code</th>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Account Name</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Debit</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Credit</th>
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="py-1 px-3 font-mono">{{ $row->account_code ?? optional($row->account)->account_code }}</td>
                    <td class="py-1 px-3">{{ $row->account_name ?? optional($row->account)->account_name }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ isset($row->debit_balance) && $row->debit_balance > 0 ? number_format($row->debit_balance, 2) : '' }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ isset($row->credit_balance) && $row->credit_balance > 0 ? number_format($row->credit_balance, 2) : '' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="py-4 text-center text-gray-500">No data available.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 border-gray-300"><tr>
                <td colspan="2" class="py-2 px-3 text-right font-semibold">Totals:</td>
                <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($totals['total_debit'] ?? 0, 2) }}</td>
                <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($totals['total_credit'] ?? 0, 2) }}</td>
            </tr></tfoot>
        </table>
    </div>
</div>
