<x-accounting::app-layout title="Currency Revaluation">
    <x-slot name="header">
        <x-accounting::page-header title="Currency Revaluation" :showSearch="false" backRoute="accounting.dashboard" />
    </x-slot>
    @php($input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm')

    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error') || $previewError)<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') ?? $previewError }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Restate foreign-currency receivables, payables and bank balances at the closing rate{{ $base ? ' (base currency '.$base.')' : '' }}; the difference is booked as an unrealised exchange gain or loss.</p>

        @can('fx-revaluation.run')
        <form method="GET" action="{{ route('accounting.fx-revaluation.index') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
            <div><x-accounting::label for="as_of_date" value="As of" /><x-accounting::input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOf" required /></div>
            @foreach ($currencies as $currency)
                <div><x-accounting::label :for="'rate-'.$currency['id']" :value="$currency['code'].' closing rate'" /><input id="rate-{{ $currency['id'] }}" name="rates[{{ $currency['id'] }}]" type="number" step="any" value="{{ $currency['rate'] }}" class="{{ $input }}"></div>
            @endforeach
            <div class="flex items-end"><button class="px-4 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest">Calculate</button></div>
        </form>
        @endcan

        @if ($preview)
        <div class="bg-white shadow rounded-lg p-5 space-y-4">
            <h3 class="font-semibold text-gray-800">Preview as of {{ $preview['as_of_date'] }} <span class="text-xs font-normal text-gray-500">gain {{ number_format((float) $preview['total_gain'], 2) }} · loss {{ number_format((float) $preview['total_loss'], 2) }}</span></h3>
            <div class="overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Account</th><th class="py-2 px-3 text-right font-medium text-gray-600">Foreign balance</th><th class="py-2 px-3 text-right font-medium text-gray-600">Rate</th><th class="py-2 px-3 text-right font-medium text-gray-600">Carrying ({{ $base }})</th><th class="py-2 px-3 text-right font-medium text-gray-600">Revalued ({{ $base }})</th><th class="py-2 px-3 text-right font-medium text-gray-600">Adjustment</th></tr></thead>
                <tbody>
                @forelse ($preview['rows'] as $row)
                    <tr class="border-t"><td class="py-2 px-3">{{ $row['account_code'] }} {{ $row['account_name'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['foreign_balance'], 2) }} {{ $row['currency_code'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $row['rate'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['carrying_base'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $row['revalued_base'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums {{ (float) $row['adjustment'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format((float) $row['adjustment'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-gray-500">Nothing to revalue: every foreign-currency balance is already stated at these rates.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            @can('fx-revaluation.run')
            @if ($preview['rows'])
            <form method="POST" action="{{ route('accounting.fx-revaluation.store') }}" class="grid gap-4 md:grid-cols-4 border-t pt-4" x-data="{ reverse: false }">
                @csrf
                <input type="hidden" name="as_of_date" value="{{ $preview['as_of_date'] }}">
                @foreach ($currencies as $currency)<input type="hidden" name="rates[{{ $currency['id'] }}]" value="{{ $currency['rate'] }}">@endforeach
                <div class="md:col-span-2"><x-accounting::label for="gain_loss_account_id" value="Unrealised gain/loss account" /><select id="gain_loss_account_id" name="gain_loss_account_id" required class="{{ $input }}"><option value="">Select an income or expense account</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}" @selected($account['account_code'] === $defaultAccountCode)>{{ $account['account_code'] }} - {{ $account['account_name'] }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><x-accounting::label for="notes" value="Narration" /><x-accounting::input id="notes" name="notes" class="mt-1 block w-full" /></div>
                <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="hidden" name="auto_reverse" value="0"><input type="checkbox" name="auto_reverse" value="1" x-model="reverse"> Reverse automatically at the start of the next period</label>
                <div x-show="reverse"><x-accounting::label for="reversal_date" value="Reversal date" /><x-accounting::input id="reversal_date" name="reversal_date" type="date" class="mt-1 block w-full" /></div>
                <div class="md:col-span-4"><button class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Post revaluation</button></div>
            </form>
            @endif
            @endcan
        </div>
        @endif

        <div class="bg-white shadow rounded-lg p-5 space-y-4">
            <h3 class="font-semibold text-gray-800">Exchange rates <span class="text-xs font-normal text-gray-500">1 unit = rate {{ $base }}; a revaluation date picks the latest rate on or before it</span></h3>
            @can('fx-revaluation.rates')
            @if (count($currencies))
            <form method="POST" action="{{ route('accounting.fx-revaluation.rates.store') }}" class="grid gap-4 md:grid-cols-5">
                @csrf
                <div><x-accounting::label for="rate_currency" value="Currency" /><select id="rate_currency" name="currency_id" class="{{ $input }}">@foreach ($currencies as $currency)<option value="{{ $currency['id'] }}">{{ $currency['code'] }}</option>@endforeach</select></div>
                <div><x-accounting::label for="rate_date" value="Date" /><x-accounting::input id="rate_date" name="rate_date" type="date" class="mt-1 block w-full" :value="$today" required /></div>
                <div><x-accounting::label for="rate_value" value="Rate" /><input id="rate_value" name="rate" type="number" step="any" required class="{{ $input }}"></div>
                <div><x-accounting::label for="rate_source" value="Source" /><x-accounting::input id="rate_source" name="source" class="mt-1 block w-full" /></div>
                <div class="flex items-end"><button class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Save rate</button></div>
            </form>
            @endif
            @endcan
            <div class="overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Date</th><th class="py-2 px-3 text-left font-medium text-gray-600">Currency</th><th class="py-2 px-3 text-right font-medium text-gray-600">Rate</th><th class="py-2 px-3 text-left font-medium text-gray-600">Source</th><th></th></tr></thead>
                <tbody>
                @forelse ($rates as $rate)
                    <tr class="border-t"><td class="py-2 px-3">{{ $rate['rate_date'] }}</td><td class="py-2 px-3">{{ $rate['currency'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ (float) $rate['rate'] }}</td><td class="py-2 px-3">{{ $rate['source'] ?? '—' }}</td>
                        <td class="py-2 px-3 text-right">@can('fx-revaluation.rates')<form method="POST" action="{{ route('accounting.fx-revaluation.rates.destroy', $rate['id']) }}" class="inline">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Remove</button></form>@endcan</td></tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-gray-500">No dated rates yet: revaluations use each currency's current rate.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="bg-white shadow rounded-lg p-5 space-y-3">
            <h3 class="font-semibold text-gray-800">History</h3>
            <div class="overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">As of</th><th class="py-2 px-3 text-left font-medium text-gray-600">Voucher</th><th class="py-2 px-3 text-right font-medium text-gray-600">Gain</th><th class="py-2 px-3 text-right font-medium text-gray-600">Loss</th><th class="py-2 px-3 text-left font-medium text-gray-600">Reversal</th></tr></thead>
                <tbody>
                @forelse ($revaluations as $revaluation)
                    <tr class="border-t"><td class="py-2 px-3"><a href="{{ route('accounting.fx-revaluation.show', $revaluation['id']) }}" class="font-medium text-indigo-700 hover:underline">{{ $revaluation['as_of_date'] }}</a></td><td class="py-2 px-3">{{ $revaluation['voucher_number'] ?? '—' }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $revaluation['total_gain'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $revaluation['total_loss'], 2) }}</td><td class="py-2 px-3">{{ $revaluation['reversal_date'] ? ($revaluation['reversal_voucher_number'] ?? '').' on '.$revaluation['reversal_date'] : 'not reversed' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-gray-500">No revaluations yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div></div>
</x-accounting::app-layout>
