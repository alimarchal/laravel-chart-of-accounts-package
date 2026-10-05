<x-accounting::app-layout title="Bank Statements">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bank Statements</h2>
            <div class="flex gap-2">
                @can('bank-statements.import')<a href="{{ route('accounting.bank-statements.import') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">Import statement</a>@endcan
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <p class="text-sm text-gray-600">Import statements from your bank, match them to the ledger and reconcile.</p>
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
