<x-accounting::app-layout :title="$document ? 'Edit Document' : 'New Document'">
    <x-slot name="header">
        <x-accounting::page-header :title="($document ? 'Edit ' : 'New ').ucwords(str_replace('_', ' ', $kind))" :showSearch="false" backRoute="accounting.party-documents.index" />
    </x-slot>
    @php
        $titles = ['invoice' => 'Customer', 'credit_note' => 'Customer', 'bill' => 'Supplier', 'debit_note' => 'Supplier'];
        $lines = old('lines') ?? ($document ? collect($document['lines'])->map(fn ($l) => ['chart_of_account_id' => $l['chart_of_account_id'], 'description' => $l['description'], 'cost_center_id' => $l['cost_center_id'], 'quantity' => (float) $l['quantity'], 'unit_price' => (float) $l['unit_price'], 'tax_code_id' => $l['tax_code_id']])->all() : [['chart_of_account_id' => '', 'description' => '', 'cost_center_id' => '', 'quantity' => 1, 'unit_price' => '', 'tax_code_id' => '']]);
        $v = fn (string $key, $default = '') => old($key, $document[$key] ?? $default);
        $input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm';
    @endphp
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <form method="POST" action="{{ $document ? route('accounting.party-documents.update', $document['id']) : route('accounting.party-documents.store') }}" class="space-y-4"
              x-data="{ lines: @js($lines), inclusive: @js((bool) $v('prices_include_tax', false)), rates: @js($taxCodes->pluck('rate', 'id')),
                  get totals() { let s = 0, t = 0; for (const l of this.lines) { const g = Number(l.quantity || 0) * Number(l.unit_price || 0); const r = Number(this.rates[l.tax_code_id] || 0); const lt = this.inclusive ? g * r / (100 + r) : g * r / 100; t += lt; s += this.inclusive ? g - lt : g } return `Subtotal ${s.toFixed(2)} · Tax ${t.toFixed(2)} · Total ${(s + t).toFixed(2)}` } }">
            @csrf @if($document) @method('PUT') @endif
            <input type="hidden" name="kind" value="{{ $kind }}">
            <div class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
                <div class="md:col-span-2"><x-accounting::label for="party_id" :value="$titles[$kind]" /><select id="party_id" name="party_id" required class="{{ $input }}"><option value="">Select</option>@foreach ($parties as $party)<option value="{{ $party->id }}" @selected((string) $v('party_id') === (string) $party->id)>{{ $party->code }} - {{ $party->name }}</option>@endforeach</select></div>
                <div><x-accounting::label for="issue_date" value="Date" /><x-accounting::input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full" :value="$v('issue_date', $today)" required /></div>
                <div><x-accounting::label for="due_date" value="Due date (default: payment terms)" /><x-accounting::input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="$v('due_date')" /></div>
                <div class="md:col-span-2"><x-accounting::label for="reference" :value="$kind === 'bill' ? 'Supplier\'s invoice number' : 'Reference (e.g. order number)'" /><x-accounting::input id="reference" name="reference" class="mt-1 block w-full" :value="$v('reference')" /></div>
                <label class="flex items-center gap-2 pt-7 text-sm md:col-span-2"><input type="hidden" name="prices_include_tax" value="0"><input type="checkbox" name="prices_include_tax" value="1" x-model="inclusive"> Prices include tax</label>
            </div>
            <div class="bg-white shadow rounded-lg p-5 space-y-3">
                <h3 class="font-semibold text-gray-800">Lines</h3>
                <template x-for="(line, i) in lines" :key="i">
                    <div class="border rounded-md p-3 space-y-2">
                        <div class="grid gap-2 md:grid-cols-[2fr_2fr_1fr_1fr_1.4fr_auto] items-center">
                            <select :name="`lines[${i}][chart_of_account_id]`" x-model="line.chart_of_account_id" class="border-gray-300 rounded-md text-sm"><option value="">Account</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select>
                            <input type="text" placeholder="Description" :name="`lines[${i}][description]`" x-model="line.description" class="border-gray-300 rounded-md text-sm">
                            <input type="number" step="any" min="0" placeholder="Qty" :name="`lines[${i}][quantity]`" x-model="line.quantity" class="border-gray-300 rounded-md text-sm">
                            <input type="number" step="any" min="0" placeholder="Price" :name="`lines[${i}][unit_price]`" x-model="line.unit_price" class="border-gray-300 rounded-md text-sm">
                            <select :name="`lines[${i}][tax_code_id]`" x-model="line.tax_code_id" class="border-gray-300 rounded-md text-sm"><option value="">No tax</option>@foreach ($taxCodes as $code)<option value="{{ $code['id'] }}">{{ $code['code'] }} @if($code['rate'] !== null)({{ (float) $code['rate'] }}%)@endif</option>@endforeach</select>
                            <button type="button" @click="lines.length > 1 && lines.splice(i, 1)" class="text-red-700 text-sm hover:underline" x-show="lines.length > 1">Remove</button>
                        </div>
                        @if ($costCenters->count())<select :name="`lines[${i}][cost_center_id]`" x-model="line.cost_center_id" class="border-gray-300 rounded-md text-sm"><option value="">No cost center</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>@endforeach</select>@endif
                    </div>
                </template>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" @click="lines.push({chart_of_account_id: '', description: '', cost_center_id: '', quantity: 1, unit_price: '', tax_code_id: ''})" class="px-3 py-1.5 rounded-md border border-gray-300 text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Add line</button>
                    <div class="text-sm tabular-nums" x-text="totals"></div>
                </div>
                <div><x-accounting::label for="notes" value="Notes" /><x-accounting::input id="notes" name="notes" class="mt-1 block w-full" :value="$v('notes')" /></div>
            </div>
            <button class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Save draft</button>
        </form>
    </div></div>
</x-accounting::app-layout>
