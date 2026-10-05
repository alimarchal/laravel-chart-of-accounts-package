<x-accounting::app-layout :title="$entry ? 'Edit Recurring Entry' : 'New Recurring Entry'">
    <x-slot name="header">
        <x-accounting::page-header :title="$entry ? 'Edit Recurring Entry' : 'New Recurring Entry'" :showSearch="false" backRoute="accounting.recurring-entries.index" />
    </x-slot>
    @php
        $old = old('lines');
        $lines = $old ?? ($entry ? collect($entry['lines'])->map(fn ($l) => ['chart_of_account_id' => $l['chart_of_account_id'], 'cost_center_id' => $l['cost_center_id'], 'debit' => (float) $l['debit'] > 0 ? (float) $l['debit'] : '', 'credit' => (float) $l['credit'] > 0 ? (float) $l['credit'] : '', 'description' => $l['description']])->all() : [['chart_of_account_id' => '', 'cost_center_id' => '', 'debit' => '', 'credit' => '', 'description' => ''], ['chart_of_account_id' => '', 'cost_center_id' => '', 'debit' => '', 'credit' => '', 'description' => '']]);
        $v = fn (string $key, $default = '') => old($key, $entry[$key] ?? $default);
    @endphp
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <form method="POST" action="{{ $entry ? route('accounting.recurring-entries.update', $entry['id']) : route('accounting.recurring-entries.store') }}" class="space-y-4"
              x-data="{ lines: @js($lines), frequency: @js($v('frequency', 'monthly')),
                  cents(v) { return Math.round(Number(v || 0) * 100) },
                  get debit() { return this.lines.reduce((s, l) => s + this.cents(l.debit), 0) },
                  get credit() { return this.lines.reduce((s, l) => s + this.cents(l.credit), 0) } }">
            @csrf @if($entry) @method('PUT') @endif
            <div class="bg-white shadow rounded-lg p-5 space-y-4">
                <h3 class="font-semibold text-gray-800">Schedule</h3>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="xl:col-span-2"><x-accounting::label for="name" value="Name" /><x-accounting::input id="name" name="name" class="mt-1 block w-full" :value="$v('name')" required /></div>
                    <div><x-accounting::label for="frequency" value="Repeats" /><select id="frequency" name="frequency" x-model="frequency" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">@foreach ($frequencies as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div><x-accounting::label for="interval" value="Every" /><x-accounting::input id="interval" name="interval" type="number" min="1" class="mt-1 block w-full" :value="$v('interval', 1)" /></div>
                    <div><x-accounting::label for="start_date" value="First entry on" /><x-accounting::input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="substr((string) $v('start_date', $today), 0, 10)" :disabled="$entry && $entry['runs_count'] > 0" /></div>
                    <div x-show="['monthly','quarterly','yearly'].includes(frequency)"><x-accounting::label for="day_of_month" value="Day of month" /><x-accounting::input id="day_of_month" name="day_of_month" type="number" min="1" max="31" class="mt-1 block w-full" :value="$v('day_of_month')" placeholder="Same as first" /></div>
                    <div><x-accounting::label for="end_date" value="Stop after (date)" /><x-accounting::input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="substr((string) $v('end_date'), 0, 10)" /></div>
                    <div><x-accounting::label for="max_runs" value="Stop after (entries)" /><x-accounting::input id="max_runs" name="max_runs" type="number" min="1" class="mt-1 block w-full" :value="$v('max_runs')" /></div>
                    <div><x-accounting::label for="mode" value="Each entry is" /><select id="mode" name="mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option value="draft" @selected($v('mode', 'draft') === 'draft')>Created as a draft</option><option value="post" @selected($v('mode', 'draft') === 'post')>Posted automatically</option></select></div>
                    <div><x-accounting::label for="voucher_type_id" value="Voucher type" /><select id="voucher_type_id" name="voucher_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option value="">Journal voucher (default)</option>@foreach ($voucherTypes as $type)<option value="{{ $type->id }}" @selected((string) $v('voucher_type_id') === (string) $type->id)>{{ $type->code }} - {{ $type->name }}</option>@endforeach</select></div>
                    <div><x-accounting::label for="reference" value="Reference" /><x-accounting::input id="reference" name="reference" class="mt-1 block w-full" :value="$v('reference')" /></div>
                    <div><x-accounting::label for="description" value="Narration" /><x-accounting::input id="description" name="description" class="mt-1 block w-full" :value="$v('description')" /></div>
                </div>
            </div>
            <div class="bg-white shadow rounded-lg p-5 space-y-3">
                <h3 class="font-semibold text-gray-800">Lines <span class="text-xs font-normal text-gray-500">debits must equal credits, base currency</span></h3>
                <template x-for="(line, i) in lines" :key="i">
                    <div class="grid gap-2 md:grid-cols-[2fr_1.2fr_1fr_1fr_1.5fr_auto] items-center">
                        <select :name="`lines[${i}][chart_of_account_id]`" x-model="line.chart_of_account_id" class="border-gray-300 rounded-md text-sm"><option value="">Account</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select>
                        <select :name="`lines[${i}][cost_center_id]`" x-model="line.cost_center_id" class="border-gray-300 rounded-md text-sm"><option value="">No cost center</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>@endforeach</select>
                        <input type="number" step="0.01" min="0" placeholder="Debit" :name="`lines[${i}][debit]`" x-model="line.debit" @input="if (line.debit) line.credit = ''" class="border-gray-300 rounded-md text-sm">
                        <input type="number" step="0.01" min="0" placeholder="Credit" :name="`lines[${i}][credit]`" x-model="line.credit" @input="if (line.credit) line.debit = ''" class="border-gray-300 rounded-md text-sm">
                        <input type="text" placeholder="Line note" :name="`lines[${i}][description]`" x-model="line.description" class="border-gray-300 rounded-md text-sm">
                        <button type="button" @click="lines.length > 2 && lines.splice(i, 1)" class="text-red-700 text-sm hover:underline" x-show="lines.length > 2">Remove</button>
                    </div>
                </template>
                <div class="flex items-center justify-between border-t pt-3">
                    <button type="button" @click="lines.push({chart_of_account_id: '', cost_center_id: '', debit: '', credit: '', description: ''})" class="px-3 py-1.5 rounded-md border border-gray-300 text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Add line</button>
                    <div class="text-sm tabular-nums" :class="debit === credit && debit > 0 ? 'text-emerald-700' : 'text-red-700'" x-text="`Debit ${(debit / 100).toFixed(2)} · Credit ${(credit / 100).toFixed(2)}` + (debit === credit && debit > 0 ? ' · balanced' : ` · difference ${(Math.abs(debit - credit) / 100).toFixed(2)}`)"></div>
                </div>
            </div>
            <div class="flex justify-end"><button type="submit" class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">{{ $entry ? 'Save changes' : 'Create recurring entry' }}</button></div>
        </form>
    </div></div>
</x-accounting::app-layout>
