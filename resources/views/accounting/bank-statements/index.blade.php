<x-accounting::app-layout title="Bank Statements">
    <x-slot name="header"><x-accounting::page-header title="Bank Statements" :showSearch="false" :showRefresh="false" :showPrint="false" :backRoute="route('accounting.dashboard')"><x-slot name="actions">@can('bank-statements.import')<a href="{{ route('accounting.bank-statements.import') }}" class="acc-btn acc-btn--primary">Import statement</a>@endcan</x-slot></x-accounting::page-header></x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <p class="text-sm text-gray-600">Import statements from your bank, match them to the ledger and reconcile.</p>
        <x-accounting::list-filters :action="route('accounting.bank-statements.index')" :values="$filters" :fields="[
            ['name' => 'search', 'label' => 'Search', 'placeholder' => 'File or bank account'],
            ['name' => 'bank_account_id', 'label' => 'Bank account', 'type' => 'select', 'options' => collect($banks)->mapWithKeys(fn ($bank) => [$bank['id'] => $bank['name']])->all()],
            ['name' => 'date_from', 'label' => 'Period from', 'type' => 'date'],
            ['name' => 'date_to', 'label' => 'Period to', 'type' => 'date'],
            ['name' => 'unmatched', 'label' => 'Lines', 'type' => 'select', 'options' => ['1' => 'With unmatched lines']],
        ]" />
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">File</th><th class="py-2 px-3 text-left font-medium text-gray-600">Bank account</th><th class="py-2 px-3 text-left font-medium text-gray-600">Period</th><th class="py-2 px-3 text-right font-medium text-gray-600">Closing balance</th><th class="py-2 px-3 text-right font-medium text-gray-600">Transactions</th><th class="py-2 px-3 text-left font-medium text-gray-600">Status</th></tr></thead>
            <tbody>
            @forelse ($statements as $statement)
                <tr class="border-t">
                    <td class="py-2 px-3"><a href="{{ route('accounting.bank-statements.show', $statement['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $statement['file_name'] }}</a></td>
                    <td class="py-2 px-3">{{ $statement['bank'] }}</td>
                    <td class="py-2 px-3">{{ $statement['from_date'] }} → {{ $statement['to_date'] }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ $statement['closing_balance'] === null ? '—' : number_format((float) $statement['closing_balance'], 2) }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ $statement['lines_count'] }}</td>
                    <td class="py-2 px-3">
                        @if ($statement['reconciliation_id'])<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">reconciled</span>
                        @elseif ($statement['unmatched_count'] > 0)<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-800">{{ $statement['unmatched_count'] }} to match</span>
                        @else<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800">ready to reconcile</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-gray-500">No statements imported yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
