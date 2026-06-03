<div>
    <x-accounting::validation-errors class="mb-4" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <x-accounting::label for="entry_date" value="Entry Date" />
            <x-accounting::input id="entry_date" type="date" class="mt-1 block w-full" wire:model="entry_date" required />
        </div>
        <div>
            <x-accounting::label for="accounting_period_id" value="Accounting Period" />
            <select id="accounting_period_id" wire:model="accounting_period_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                <option value="">Select Period</option>
                @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-accounting::label for="currency_id" value="Currency" />
            <select id="currency_id" wire:model="currency_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
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
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Account</th>
                        <th class="py-2 px-3 text-left font-medium text-gray-600">Cost Center</th>
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
                            <select wire:model="lines.{{ $index }}.chart_of_account_id" class="w-full border-gray-300 rounded-md text-sm" required>
                                <option value="">Select Account</option>
                                @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->account_code }} — {{ $acc->account_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1 px-2">
                            <select wire:model="lines.{{ $index }}.cost_center_id" class="w-full border-gray-300 rounded-md text-sm">
                                <option value="">None</option>
                                @foreach ($costCenters as $cc)
                                <option value="{{ $cc->id }}">{{ $cc->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" min="0" wire:model="lines.{{ $index }}.debit" class="w-full border-gray-300 rounded-md text-sm text-right" />
                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" min="0" wire:model="lines.{{ $index }}.credit" class="w-full border-gray-300 rounded-md text-sm text-right" />
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
                    <tr>
                        <td colspan="2" class="py-2 px-3 text-right font-semibold text-sm text-gray-700">Totals:</td>
                        <td class="py-2 px-3 text-right font-semibold text-sm {{ abs($this->totalDebits() - $this->totalCredits()) < 0.01 ? 'text-green-700' : 'text-red-600' }}">{{ number_format($this->totalDebits(), 2) }}</td>
                        <td class="py-2 px-3 text-right font-semibold text-sm {{ abs($this->totalDebits() - $this->totalCredits()) < 0.01 ? 'text-green-700' : 'text-red-600' }}">{{ number_format($this->totalCredits(), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    @if (abs($this->totalDebits() - $this->totalCredits()) >= 0.01)
                    <tr><td colspan="6" class="py-1 px-3 text-center text-xs text-red-600">⚠ Debits and credits must balance.</td></tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>

    <div class="flex justify-end mt-6">
        <x-accounting::button wire:click="save" wire:loading.attr="disabled">Save Draft</x-accounting::button>
    </div>
</div>
