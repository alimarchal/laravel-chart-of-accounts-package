<div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div><x-accounting::label for="date_from" value="Date From" /><x-accounting::input id="date_from" type="date" class="mt-1 block w-full" wire:model.live="date_from" /></div>
        <div><x-accounting::label for="date_to" value="Date To" /><x-accounting::input id="date_to" type="date" class="mt-1 block w-full" wire:model.live="date_to" /></div>
        <div><x-accounting::label for="account_id" value="Account" />
            <select id="account_id" wire:model.live="account_id" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                <option value="">All Accounts</option>
                @foreach ($accounts as $acc)<option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->account_name }}</option>@endforeach
            </select>
        </div>
        <div><x-accounting::label for="status" value="Status" />
            <select id="status" wire:model.live="status" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                <option value="">All</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200 rounded-md">
            <thead class="bg-gray-50"><tr>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Date</th>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Reference</th>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                <th class="py-2 px-3 text-left font-medium text-gray-600">Description</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Debit</th>
                <th class="py-2 px-3 text-right font-medium text-gray-600">Credit</th>
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="py-1 px-3">{{ optional($row->journalEntry)->entry_date?->format('Y-m-d') }}</td>
                    <td class="py-1 px-3 font-mono">{{ optional($row->journalEntry)->reference }}</td>
                    <td class="py-1 px-3">{{ optional($row->account)->account_code }}</td>
                    <td class="py-1 px-3">{{ $row->description }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ $row->debit > 0 ? number_format($row->debit, 2) : '' }}</td>
                    <td class="py-1 px-3 text-right font-mono">{{ $row->credit > 0 ? number_format($row->credit, 2) : '' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="py-4 text-center text-gray-500">No entries found.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 border-gray-300"><tr>
                <td colspan="4" class="py-2 px-3 text-right font-semibold">Totals:</td>
                <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($totals['total_debit'] ?? 0, 2) }}</td>
                <td class="py-2 px-3 text-right font-semibold font-mono">{{ number_format($totals['total_credit'] ?? 0, 2) }}</td>
            </tr></tfoot>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</div>
