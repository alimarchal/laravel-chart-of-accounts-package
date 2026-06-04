<div x-data="journalEntryForm()" x-init="init()">
    <x-accounting::validation-errors class="mb-4" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <x-accounting::label for="entry_date" value="Entry Date" />
            <x-accounting::input id="entry_date" type="date" class="mt-1 block w-full" wire:model="entry_date" required />
        </div>
        <div>
            <x-accounting::label for="accounting_period_id" value="Accounting Period" />
            <select id="accounting_period_id" wire:model="accounting_period_id"
                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                <option value="">Select Period</option>
                @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-accounting::label for="currency_id" value="Currency" />
            <select id="currency_id" wire:model="currency_id"
                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                <option value="">Select Currency</option>
                @foreach ($currencies as $cur)
                <option value="{{ $cur->id }}">{{ $cur->code }} - {{ $cur->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-accounting::label for="fx_rate_to_base" value="FX Rate to Base" />
            <x-accounting::input id="fx_rate_to_base" type="number" step="0.00000001" class="mt-1 block w-full" wire:model="fx_rate_to_base" required />
        </div>
        <div>
            <x-accounting::label for="reference" value="Reference" />
            <x-accounting::input id="reference" type="text" class="mt-1 block w-full" wire:model="reference" />
        </div>
        <div>
            <x-accounting::label for="description" value="Description" />
            <x-accounting::input id="description" type="text" class="mt-1 block w-full" wire:model="description" />
        </div>
    </div>

    <div class="mt-6">
        <div class="flex justify-between items-center mb-2">
            <h3 class="text-sm font-semibold text-gray-700">Journal Entry Lines</h3>
            <button type="button" wire:click="addLine" class="text-xs px-3 py-1 bg-green-700 text-white rounded hover:bg-green-600">+ Add Line</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 rounded-md">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 min-w-[220px]">Account</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600 min-w-[160px]">Cost Center</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600 w-28">Debit</th>
                        <th class="py-2 px-3 text-right font-medium text-gray-600 w-28">Credit</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Note</th>
                        <th class="py-2 px-3 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $index => $line)
                    <tr class="border-t border-gray-100">
                        <td class="py-1 px-2">
                            <select
                                wire:model="lines.{{ $index }}.chart_of_account_id"
                                class="je-account-select w-full border-gray-300 rounded-md text-sm"
                                data-index="{{ $index }}"
                                required>
                                <option value="">— Select Account —</option>
                                @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ (string)($line['chart_of_account_id'] ?? '') === (string)$acc->id ? 'selected' : '' }}>
                                    {{ $acc->account_code }} — {{ $acc->account_name }}
                                </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1 px-2">
                            <select
                                wire:model="lines.{{ $index }}.cost_center_id"
                                class="je-cc-select w-full border-gray-300 rounded-md text-sm"
                                data-index="{{ $index }}">
                                <option value="">None</option>
                                @foreach ($costCenters as $cc)
                                <option value="{{ $cc->id }}" {{ (string)($line['cost_center_id'] ?? '') === (string)$cc->id ? 'selected' : '' }}>
                                    {{ $cc->name }}
                                </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" min="0"
                                wire:model.live="lines.{{ $index }}.debit"
                                class="w-full border-gray-300 rounded-md text-sm text-right" />
                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" min="0"
                                wire:model.live="lines.{{ $index }}.credit"
                                class="w-full border-gray-300 rounded-md text-sm text-right" />
                        </td>
                        <td class="py-1 px-2">
                            <input type="text" wire:model="lines.{{ $index }}.description" class="w-full border-gray-300 rounded-md text-sm" />
                        </td>
                        <td class="py-1 px-2">
                            <button type="button" wire:click="removeLine({{ $index }})" class="text-red-500 hover:text-red-700 text-xs">✕</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    @php $isBalanced = abs($this->totalDebits() - $this->totalCredits()) < 0.01 && $this->totalDebits() > 0; @endphp
                    <tr>
                        <td colspan="2" class="py-2 px-3 text-right font-semibold text-sm text-gray-700">Totals:</td>
                        <td class="py-2 px-3 text-right font-semibold text-sm {{ $isBalanced ? 'text-green-700' : 'text-red-600' }}">
                            {{ number_format($this->totalDebits(), 2) }}
                        </td>
                        <td class="py-2 px-3 text-right font-semibold text-sm {{ $isBalanced ? 'text-green-700' : 'text-red-600' }}">
                            {{ number_format($this->totalCredits(), 2) }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                    @unless($isBalanced)
                    <tr>
                        <td colspan="6" class="py-1 px-3 text-center text-xs text-red-600">
                            ⚠ Debits ({{ number_format($this->totalDebits(), 2) }}) and credits ({{ number_format($this->totalCredits(), 2) }}) must balance.
                            Difference: {{ number_format(abs($this->totalDebits() - $this->totalCredits()), 2) }}
                        </td>
                    </tr>
                    @endunless
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Balance status --}}
    <div class="mt-3 flex items-center gap-2 text-sm">
        @if($isBalanced)
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 text-green-700 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Balanced — ready to save
            </span>
        @else
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-red-100 text-red-700 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                Not balanced — cannot save
            </span>
        @endif
    </div>

    <div class="flex justify-end mt-4">
        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            {{ $isBalanced ? '' : 'disabled' }}
            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 {{ $isBalanced ? 'hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2' : 'opacity-50 cursor-not-allowed' }}">
            Save Draft
        </button>
    </div>
</div>

@script
<script>
    function initJESelect2() {
        if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') return;

        $('.je-account-select').each(function () {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
            $el.select2({ width: '100%', placeholder: '— Select Account —', allowClear: true });
            $el.off('change.je').on('change.je', function () {
                this.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        $('.je-cc-select').each(function () {
            var $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
            $el.select2({ width: '100%', placeholder: 'None', allowClear: true });
            $el.off('change.je').on('change.je', function () {
                this.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    document.addEventListener('livewire:updated', initJESelect2);
    initJESelect2();
</script>
@endscript
