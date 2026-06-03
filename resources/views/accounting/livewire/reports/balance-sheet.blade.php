<div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200 rounded-md">
            <thead class="bg-gray-50"><tr>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Type</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Balance</th>
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="py-1 px-3">{{ $row->account_name ?? optional($row->account)->account_name }}</td>
                    <td class="py-1 px-3">{{ $row->type ?? $row->account_type }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ number_format($row->balance ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="py-4 text-center text-gray-500">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
