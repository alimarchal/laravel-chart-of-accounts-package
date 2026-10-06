<x-accounting::app-layout title="Tax">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tax</h2>
            <div class="flex gap-2">
                @can('tax-entries.create')<a href="{{ route('accounting.tax.entries.create') }}" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 transition">New taxed document</a>@endcan
                <a href="{{ route('accounting.tax.returns.report', ['date_from' => $monthStart, 'date_to' => $monthEnd]) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">Tax report</a>
                <a href="{{ route('accounting.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            </div>
        </div>
    </x-slot>
    @php($input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm')
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('success'))<div class="rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Tax codes and rates, taxed documents, the tax ledger and tax returns. Manage codes and rates under <a class="text-indigo-700 hover:underline" href="{{ route('accounting.tax-codes.index') }}">Tax codes</a>.</p>
        <div class="bg-white shadow rounded-lg overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Code</th><th class="py-2 px-3 text-left font-medium text-gray-600">Name</th><th class="py-2 px-3 text-left font-medium text-gray-600">Kind</th><th class="py-2 px-3 text-right font-medium text-gray-600">Rate today</th><th class="py-2 px-3 text-left font-medium text-gray-600">Tax account</th><th class="py-2 px-3 text-left font-medium text-gray-600">Jurisdiction</th></tr></thead>
            <tbody>
            @foreach ($taxCodes as $code)
                <tr class="border-t"><td class="py-2 px-3 font-medium">{{ $code['code'] }} @unless($code['is_active'])<span class="ml-1 rounded-full px-2 py-0.5 text-xs bg-gray-100 text-gray-600">inactive</span>@endunless</td><td class="py-2 px-3">{{ $code['name'] }}</td><td class="py-2 px-3">{{ $code['kind'] }}</td><td class="py-2 px-3 text-right tabular-nums">{{ $code['rate'] === null ? '—' : (float) $code['rate'].'%' }}</td><td class="py-2 px-3">{!! $code['tax_account'] ? e($code['tax_account']) : '<span class="text-amber-700">not set</span>' !!}</td><td class="py-2 px-3">{{ $code['jurisdiction'] }}</td></tr>
            @endforeach
            </tbody>
        </table></div>

        <div class="bg-white shadow rounded-lg p-5" x-data="{ code: '{{ $taxCodes[0]['id'] ?? '' }}', amount: '', inclusive: false, result: null, error: null,
            async calc() { this.error = null; const r = await fetch('{{ route('accounting.tax.calculate') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, credentials: 'same-origin', body: JSON.stringify({ tax_code_id: this.code, amount: this.amount || 0, inclusive: this.inclusive, date: '{{ $today }}' }) }); const b = await r.json(); if (r.ok) { this.result = b.data } else { this.result = null; this.error = b.message || 'Could not calculate.' } } }">
            <h3 class="font-semibold text-gray-800 mb-3">Calculator <span class="text-xs font-normal text-gray-500">tax on an amount at today's rate</span></h3>
            <form @submit.prevent="calc()" class="flex flex-wrap items-end gap-3">
                <div><x-accounting::label for="calc_code" value="Tax code" /><select id="calc_code" x-model="code" class="{{ $input }}">@foreach ($taxCodes as $code)<option value="{{ $code['id'] }}">{{ $code['code'] }} ({{ $code['kind'] }})</option>@endforeach</select></div>
                <div><x-accounting::label for="calc_amount" value="Amount" /><input id="calc_amount" type="number" step="0.01" min="0" x-model="amount" class="{{ $input }}"></div>
                <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" x-model="inclusive"> includes tax</label>
                <button class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Calculate</button>
                <div class="pb-1 text-sm tabular-nums" x-show="result" x-text="result ? `Base ${Number(result.base).toFixed(2)} · Tax ${Number(result.tax).toFixed(2)} (${Number(result.rate)}%) · Total ${Number(result.gross).toFixed(2)}` : ''"></div>
                <div class="pb-1 text-sm text-red-700" x-show="error" x-text="error"></div>
            </form>
        </div>

        @can('tax-returns.file')
        <form method="POST" action="{{ route('accounting.tax.returns.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-5">
            @csrf
            <div class="md:col-span-5"><h3 class="font-semibold text-gray-800">File a return <span class="text-xs font-normal text-gray-500">offsets the period's output tax against its input tax in one entry and books the difference to the account you pick</span></h3></div>
            <div><x-accounting::label for="period_from" value="From" /><x-accounting::input id="period_from" name="period_from" type="date" class="mt-1 block w-full" :value="old('period_from', $monthStart)" required /></div>
            <div><x-accounting::label for="period_to" value="To" /><x-accounting::input id="period_to" name="period_to" type="date" class="mt-1 block w-full" :value="old('period_to', $monthEnd)" required /></div>
            <div class="md:col-span-2"><x-accounting::label for="payable_account_id" value="Payable (or refundable) account" /><select id="payable_account_id" name="payable_account_id" required class="{{ $input }}"><option value="">Select an account</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}" @selected(old('payable_account_id') == $account['id'])>{{ $account['account_code'] }} - {{ $account['account_name'] }}</option>@endforeach</select></div>
            <div><x-accounting::label for="reference" value="Reference" /><x-accounting::input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" /></div>
            <div class="md:col-span-5"><button class="px-4 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest">File return</button></div>
        </form>
        @endcan

        <div class="bg-white shadow rounded-lg p-5 space-y-3">
            <h3 class="font-semibold text-gray-800">Filed returns</h3>
            <div class="overflow-x-auto"><table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="py-2 px-3 text-left font-medium text-gray-600">Period</th><th class="py-2 px-3 text-right font-medium text-gray-600">Output tax</th><th class="py-2 px-3 text-right font-medium text-gray-600">Input tax</th><th class="py-2 px-3 text-right font-medium text-gray-600">Net payable</th><th class="py-2 px-3 text-left font-medium text-gray-600">Entry</th><th></th></tr></thead>
                <tbody>
                @forelse ($returns as $item)
                    <tr class="border-t"><td class="py-2 px-3">{{ $item['period_from'] }} → {{ $item['period_to'] }} <span class="ml-2 text-xs text-gray-500">{{ $item['reference'] }}</span></td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $item['output_tax'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $item['input_tax'], 2) }}</td><td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $item['net_payable'], 2) }}</td><td class="py-2 px-3">{{ $item['voucher_number'] ?? '—' }}</td>
                        <td class="py-2 px-3 text-right">@can('tax-returns.file')<form method="POST" action="{{ route('accounting.tax.returns.destroy', $item['id']) }}" onsubmit="return confirm('Void this return? Its entry is reversed and the period can be filed again.')" class="inline">@csrf @method('DELETE')<button class="text-red-700 hover:underline">Void</button></form>@endcan</td></tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-gray-500">No returns filed yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div></div>
</x-accounting::app-layout>
