<x-accounting::app-layout title="Revaluation">
    <x-slot name="header">
        <x-accounting::page-header :title="'Revaluation as of '.$revaluation['as_of_date']" :showSearch="false" backRoute="accounting.fx-revaluation.index" />
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <div class="bg-white shadow rounded-lg p-5 space-y-2 text-sm">
            <div>Posted as <a class="text-indigo-700 hover:underline" href="{{ route('accounting.journal-entries.show', $revaluation['journal_entry_id']) }}">{{ $revaluation['voucher_number'] ?? 'entry #'.$revaluation['journal_entry_id'] }}</a> against {{ $revaluation['gain_loss_account'] ?? 'the gain/loss account' }}.</div>
            <div>Gain {{ number_format((float) $revaluation['total_gain'], 2) }} · Loss {{ number_format((float) $revaluation['total_loss'], 2) }} · {{ $revaluation['reversal_date'] ? 'reversed on '.$revaluation['reversal_date'].' ('.($revaluation['reversal_voucher_number'] ?? '').')' : 'not reversed' }}</div>
            @can('fx-revaluation.run')
            @if (! $revaluation['reversal_entry_id'])
            <form method="POST" action="{{ route('accounting.fx-revaluation.reverse', $revaluation['id']) }}" class="flex items-end gap-2 pt-2">@csrf
                <div><x-accounting::label for="reversal_date" value="Reversal date (default: the day after)" /><x-accounting::input id="reversal_date" name="reversal_date" type="date" class="mt-1 block" /></div>
                <button class="px-4 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest">Reverse</button>
            </form>
            @endif
            @endcan
        </div>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Account</th><th class="py-2 px-3 text-right font-medium text-gray-600">Foreign balance</th><th class="py-2 px-3 text-right font-medium text-gray-600">Rate</th><th class="py-2 px-3 text-right font-medium text-gray-600">Carrying</th><th class="py-2 px-3 text-right font-medium text-gray-600">Revalued</th><th class="py-2 px-3 text-right font-medium text-gray-600">Adjustment</th></tr></thead>
            <tbody>
            @foreach ($revaluation['lines'] as $line)
                <tr class="border-t"><td class="py-2 px-3">{{ $line['account'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $line['foreign_balance'], 2) }} {{ $line['currency'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $line['rate'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $line['carrying_base'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $line['revalued_base'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $line['adjustment'], 2) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div></div>
</x-accounting::app-layout>
