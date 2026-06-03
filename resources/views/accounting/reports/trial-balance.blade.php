<x-accounting::app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Trial Balance</h2>
            <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            {{-- Filter Section --}}
            <div class="bg-white shadow-sm rounded-lg p-4 border border-gray-200 no-print">
                <form method="GET" action="{{ route('accounting.reports.trial-balance') }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-accounting::label for="accounting_period_id" value="Accounting Period" />
                            <select id="accounting_period_id" name="accounting_period_id"
                                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                onchange="this.form.submit()">
                                <option value="">Custom Date</option>
                                @foreach($accountingPeriods as $period)
                                    <option value="{{ $period->id }}" {{ $periodId == $period->id ? 'selected' : '' }}>
                                        {{ $period->name }} (As of {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-accounting::label for="as_of_date" value="As of Date" />
                            <x-accounting::input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <x-accounting::reset-button :href="route('accounting.reports.trial-balance')" />
                        <x-accounting::submit-button label="Filter" />
                    </div>
                </form>
            </div>

            {{-- Summary --}}
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-4 text-center">
                    <h3 class="text-xl font-bold text-gray-800">Trial Balance</h3>
                    <p class="text-gray-600 mt-1">
                        As of {{ \Carbon\Carbon::parse($asOfDate)->format('F d, Y') }}
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-sm text-gray-600">Total Debits</div>
                        <div class="text-2xl font-bold font-mono text-blue-700">
                            {{ number_format((float) $trialBalance->total_debits, 2) }}
                        </div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-sm text-gray-600">Total Credits</div>
                        <div class="text-2xl font-bold font-mono text-green-700">
                            {{ number_format((float) $trialBalance->total_credits, 2) }}
                        </div>
                    </div>
                    <div class="p-4 rounded-lg {{ abs($trialBalance->difference) < 0.01 ? 'bg-emerald-50' : 'bg-red-50' }}">
                        <div class="text-sm text-gray-600">Difference</div>
                        <div class="text-2xl font-bold font-mono {{ abs($trialBalance->difference) < 0.01 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format((float) $trialBalance->difference, 2) }}
                        </div>
                        @if(abs($trialBalance->difference) < 0.01)
                            <div class="text-xs text-emerald-600 mt-1">✓ Balanced</div>
                        @else
                            <div class="text-xs text-red-600 mt-1">⚠️ Out of Balance</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-accounting::app-layout>
