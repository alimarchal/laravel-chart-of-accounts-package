<x-accounting::app-layout>
    <x-slot name="header">
        <x-accounting::page-header title="Trial Balance" backRoute="accounting.dashboard" :showSearch="true" :showRefresh="true" :createRoute="null" createLabel="" />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-accounting::status-message />

            <x-accounting::filter-section :action="route('accounting.reports.trial-balance')">
                <div class="grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
            </x-accounting::filter-section>

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
