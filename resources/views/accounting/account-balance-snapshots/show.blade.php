<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Balance Snapshot #{{ $snapshot->id }}</h2>
            <a href="{{ route('accounting.account-balance-snapshots.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </x-slot>
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><x-accounting::label value="Period" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($snapshot->period)->name" disabled readonly /></div>
                <div><x-accounting::label value="Account" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="optional($snapshot->account)->account_code.' - '.optional($snapshot->account)->account_name" disabled readonly /></div>
                <div><x-accounting::label value="Opening Balance" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($snapshot->opening_balance, 2)" disabled readonly /></div>
                <div><x-accounting::label value="Closing Balance" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($snapshot->closing_balance, 2)" disabled readonly /></div>
                @if (isset($snapshot->total_debits))
                <div><x-accounting::label value="Total Debits" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($snapshot->total_debits, 2)" disabled readonly /></div>
                @endif
                @if (isset($snapshot->total_credits))
                <div><x-accounting::label value="Total Credits" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="number_format($snapshot->total_credits, 2)" disabled readonly /></div>
                @endif
                <div><x-accounting::label value="Snapshot Date" /><x-accounting::input type="text" class="mt-1 block w-full bg-gray-100" :value="$snapshot->created_at->format('Y-m-d H:i')" disabled readonly /></div>
            </div>
        </div>
    </div></div>
</x-accounting::app-layout>
