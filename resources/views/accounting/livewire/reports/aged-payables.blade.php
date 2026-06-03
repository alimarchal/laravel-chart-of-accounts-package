<div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200 rounded-md">
            <thead class="bg-gray-50"><tr>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Vendor / Account</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Current</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">1–30 Days</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">31–60 Days</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">61–90 Days</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">&gt;90 Days</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Total</th>
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="py-1 px-3">{{ $row->vendor ?? $row->account_name ?? optional($row->account)->account_name }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ number_format($row->current ?? 0, 2) }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ number_format($row->days_1_30 ?? $row->period_1 ?? 0, 2) }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ number_format($row->days_31_60 ?? $row->period_2 ?? 0, 2) }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ number_format($row->days_61_90 ?? $row->period_3 ?? 0, 2) }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ number_format($row->days_over_90 ?? $row->period_4 ?? 0, 2) }}</td>
                    <td class="py-1 px-3 text-right font-semibold font-mono">{{ number_format($row->total ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="py-4 text-center text-gray-500">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
