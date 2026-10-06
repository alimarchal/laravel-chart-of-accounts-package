<x-accounting::app-layout title="Taxed Document">
    <x-slot name="header">
        <x-accounting::page-header title="Taxed Document" :showSearch="false" backRoute="accounting.tax.index" />
    </x-slot>
    @php($input = 'mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm')
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">An invoice, bill, credit or debit note, or a payment with tax withheld: the tax is worked out from the code's rate on the date and booked to its tax account.</p>
        <form method="POST" action="{{ route('accounting.tax.entries.store') }}" class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-3"
              x-data="{ type: @js(old('type', 'sale')), code: @js(old('tax_code_id', '')), amount: @js(old('amount', '')), inclusive: @js((bool) old('tax_inclusive')),
                  codes: @js($taxCodes), kinds: { sale: 'output', sale_return: 'output', purchase: 'input', purchase_return: 'input', withholding_payment: 'withheld', withholding_receipt: 'advance' },
                  labels: { sale: ['Revenue account', 'Customer / bank account (debited)'], sale_return: ['Revenue account (debited)', 'Customer / bank account (credited)'], purchase: ['Expense / asset account', 'Supplier / bank account (credited)'], purchase_return: ['Expense / asset account (credited)', 'Supplier / bank account (debited)'], withholding_payment: ['Supplier account (debited the full amount)', 'Bank account (credited the amount less the tax)'], withholding_receipt: ['Customer account (credited the full amount)', 'Bank account (debited the amount less the tax)'] },
                  get options() { return this.codes.filter(c => c.kind === this.kinds[this.type]) },
                  get withholding() { return this.type.startsWith('withholding') },
                  get preview() { const c = this.codes.find(x => String(x.id) === String(this.code)); const a = Number(this.amount || 0); if (!c || a <= 0) return ''; const r = Number(c.rate || 0); const inc = this.inclusive && !this.withholding; const t = inc ? a * r / (100 + r) : a * r / 100; const b = inc ? a - t : a; return this.withholding ? `Withheld ${t.toFixed(2)} · paid ${(a - t).toFixed(2)}` : `Amount ${b.toFixed(2)} · Tax ${t.toFixed(2)} · Total ${(b + t).toFixed(2)}` } }">
            @csrf
            <div><x-accounting::label for="type" value="Type" /><select id="type" name="type" x-model="type" @change="code = ''" class="{{ $input }}">@foreach ($types as $type)<option value="{{ $type['value'] }}">{{ $type['label'] }}</option>@endforeach</select></div>
            <div><x-accounting::label for="entry_date" value="Date" /><x-accounting::input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full" :value="old('entry_date', $today)" required /></div>
            <div><x-accounting::label for="tax_code_id" value="Tax code" /><select id="tax_code_id" name="tax_code_id" x-model="code" required class="{{ $input }}"><option value="">Select a tax code</option><template x-for="c in options" :key="c.id"><option :value="c.id" x-text="`${c.code} ${c.rate === null ? '(no rate)' : '(' + Number(c.rate) + '%)'}`"></option></template></select></div>
            <div><x-accounting::label for="amount" value="Amount" /><input id="amount" name="amount" type="number" step="0.01" min="0" x-model="amount" required class="{{ $input }}"></div>
            <label class="flex items-center gap-2 pt-7 text-sm" x-show="!withholding"><input type="hidden" name="tax_inclusive" value="0"><input type="checkbox" name="tax_inclusive" value="1" x-model="inclusive"> The amount includes the tax</label>
            <div class="pt-6 text-sm tabular-nums" x-text="preview"></div>
            <div><x-accounting::label for="account_id"><span x-text="labels[type][0]"></span></x-accounting::label><select id="account_id" name="account_id" required class="{{ $input }}"><option value="">Select an account</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}" @selected(old('account_id') == $account['id'])>{{ $account['account_code'] }} - {{ $account['account_name'] }}</option>@endforeach</select></div>
            <div><x-accounting::label for="counter_account_id"><span x-text="labels[type][1]"></span></x-accounting::label><select id="counter_account_id" name="counter_account_id" required class="{{ $input }}"><option value="">Select an account</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}" @selected(old('counter_account_id') == $account['id'])>{{ $account['account_code'] }} - {{ $account['account_name'] }}</option>@endforeach</select></div>
            <div><x-accounting::label for="cost_center_id" value="Cost center" /><select id="cost_center_id" name="cost_center_id" class="{{ $input }}"><option value="">None</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>@endforeach</select></div>
            <div><x-accounting::label for="reference" value="Document number" /><x-accounting::input id="reference" name="reference" class="mt-1 block w-full" :value="old('reference')" /></div>
            <div class="md:col-span-2"><x-accounting::label for="description" value="Narration" /><x-accounting::input id="description" name="description" class="mt-1 block w-full" :value="old('description')" /></div>
            <label class="flex items-center gap-2 text-sm md:col-span-3"><input type="hidden" name="auto_post" value="0"><input type="checkbox" name="auto_post" value="1"> Post it now (otherwise it is saved as a draft)</label>
            <div class="md:col-span-3"><button class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">Create entry</button></div>
        </form>
    </div></div>
</x-accounting::app-layout>
