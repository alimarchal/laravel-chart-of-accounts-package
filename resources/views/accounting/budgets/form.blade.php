<x-accounting::app-layout :title="$budget ? 'Edit Budget' : 'New Budget'">
    <x-slot name="header">
        <x-accounting::page-header :title="$budget ? 'Edit Budget' : 'New Budget'" :showSearch="false" backRoute="accounting.budgets.index" />
    </x-slot>
    @php
        $old = old('lines');
        $lines = $old ?? ($budget ? collect($budget['lines'])->map(fn ($l) => ['chart_of_account_id' => $l['chart_of_account_id'], 'cost_center_id' => $l['cost_center_id'], 'annual' => $l['annual'], 'monthly' => true, 'amounts' => $l['amounts']])->all() : [['chart_of_account_id' => '', 'cost_center_id' => '', 'annual' => '', 'monthly' => false, 'amounts' => (object) []]]);
        $v = fn (string $key, $default = '') => old($key, $budget[$key] ?? $default);
        $year = substr($today, 0, 4);
    @endphp
    <div class="py-6"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        @if(session('error'))<div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        <x-accounting::validation-errors />
        <p class="text-sm text-gray-600">Amounts are in the base currency: income earned and expense spent. An annual figure is spread evenly over the months.</p>
        <form method="POST" action="{{ $budget ? route('accounting.budgets.update', $budget['id']) : route('accounting.budgets.store') }}" class="space-y-4"
              x-data="{ start: @js(substr((string) $v('start_date', $year.'-01-01'), 0, 10)), end: @js(substr((string) $v('end_date', $year.'-12-31'), 0, 10)), lines: @js($lines), fromActuals: false,
                  get months() { const out = []; if (!this.start || !this.end) return out; const c = new Date(this.start.slice(0, 7) + '-01T00:00:00'); const l = new Date(this.end.slice(0, 7) + '-01T00:00:00'); while (c <= l && out.length <= 24) { out.push(c.getFullYear() + '-' + String(c.getMonth() + 1).padStart(2, '0')); c.setMonth(c.getMonth() + 1); } return out; } }">
            @csrf @if($budget) @method('PUT') @endif
            <div class="bg-white shadow rounded-lg p-5 grid gap-4 md:grid-cols-4">
                <div class="md:col-span-2"><x-accounting::label for="name" value="Name" /><x-accounting::input id="name" name="name" class="mt-1 block w-full" :value="$v('name')" required /></div>
                <div><x-accounting::label for="start_date" value="First month" /><input id="start_date" name="start_date" type="date" x-model="start" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" required></div>
                <div><x-accounting::label for="end_date" value="Last month" /><input id="end_date" name="end_date" type="date" x-model="end" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" required></div>
                <div class="md:col-span-4"><x-accounting::label for="notes" value="Notes" /><x-accounting::input id="notes" name="notes" class="mt-1 block w-full" :value="$v('notes')" /></div>
            </div>
            <div class="bg-white shadow rounded-lg p-5 space-y-3">
                <h3 class="font-semibold text-gray-800">Accounts <span class="text-xs font-normal text-gray-500">one line per account (and cost center); only income and expense accounts</span></h3>
                <template x-for="(line, i) in lines" :key="i">
                    <div class="border rounded-md p-3 space-y-2">
                        <div class="grid gap-2 md:grid-cols-[2fr_1.2fr_1fr_auto_auto] items-center">
                            <select :name="`lines[${i}][chart_of_account_id]`" x-model="line.chart_of_account_id" class="border-gray-300 rounded-md text-sm"><option value="">Select an account</option>@foreach ($accounts as $account)<option value="{{ $account['id'] }}">{{ $account['account_code'] }} - {{ $account['account_name'] }} ({{ strtolower($account['type']) }})</option>@endforeach</select>
                            <select :name="`lines[${i}][cost_center_id]`" x-model="line.cost_center_id" class="border-gray-300 rounded-md text-sm"><option value="">No cost center</option>@foreach ($costCenters as $center)<option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>@endforeach</select>
                            <input type="number" step="0.01" min="0" placeholder="Annual" x-show="!line.monthly" :name="`lines[${i}][annual]`" :disabled="line.monthly" x-model="line.annual" class="border-gray-300 rounded-md text-sm">
                            <span x-show="line.monthly"></span>
                            <button type="button" @click="line.monthly = !line.monthly" class="text-sm text-indigo-700 hover:underline" x-text="line.monthly ? 'Use annual' : 'By month'"></button>
                            <button type="button" @click="lines.length > 1 && lines.splice(i, 1)" class="text-red-700 text-sm hover:underline" x-show="lines.length > 1">Remove</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-6" x-show="line.monthly">
                            <template x-for="month in months" :key="month"><div><span class="text-xs text-gray-500" x-text="month"></span><input type="number" step="0.01" min="0" :name="line.monthly ? `lines[${i}][amounts][${month}]` : null" :value="(line.amounts || {})[month] ?? ''" @input="line.amounts = { ...(Array.isArray(line.amounts) ? {} : line.amounts), [month]: $event.target.value }" class="block w-full border-gray-300 rounded-md text-sm"></div></template>
                        </div>
                    </div>
                </template>
                <button type="button" @click="lines.push({chart_of_account_id: '', cost_center_id: '', annual: '', monthly: false, amounts: {}})" class="px-3 py-1.5 rounded-md border border-gray-300 text-xs font-semibold uppercase tracking-widest hover:bg-gray-50">Add account</button>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="fromActuals"> Also add every account that had income or expenses in the same months last year, raised by <input type="number" step="any" name="from_actuals[uplift_percent]" :disabled="!fromActuals" class="w-20 border-gray-300 rounded-md text-sm"> %</label>
            </div>
            <button class="px-4 py-2 bg-green-700 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600">{{ $budget ? 'Save changes' : 'Create budget' }}</button>
        </form>
    </div></div>
</x-accounting::app-layout>
