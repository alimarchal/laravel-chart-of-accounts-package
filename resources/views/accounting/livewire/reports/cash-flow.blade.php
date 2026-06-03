<div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div><x-accounting::label for="date_from" value="Date From" /><x-accounting::input id="date_from" type="date" class="mt-1 block w-full" wire:model.live="date_from" /></div>
        <div><x-accounting::label for="date_to" value="Date To" /><x-accounting::input id="date_to" type="date" class="mt-1 block w-full" wire:model.live="date_to" /></div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200 rounded-md">
            <thead class="bg-gray-50"><tr>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Description</th>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Category</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Amount</th>
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="py-1 px-3">{{ $row->description ?? $row->account_name ?? optional($row->account)->account_name }}</td>
                    <td class="py-1 px-3">{{ $row->category ?? '' }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ number_format($row->amount ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="py-4 text-center text-gray-500">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
