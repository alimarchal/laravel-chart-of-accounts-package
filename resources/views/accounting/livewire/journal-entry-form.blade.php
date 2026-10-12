@php
    $money = fn ($value) => (float) $value > 0 ? number_format((float) $value, 2, '.', '') : '';
    $initialLines = collect($lines)->values()->map(fn ($line, $i) => [
        'uid' => $i + 1,
        'chart_of_account_id' => (string) ($line['chart_of_account_id'] ?? ''),
        'cost_center_id' => (string) ($line['cost_center_id'] ?? ''),
        'debit' => $money($line['debit'] ?? 0),
        'credit' => $money($line['credit'] ?? 0),
        'description' => (string) ($line['description'] ?? ''),
    ])->all();
    $baseCurrencyId = optional($currencies->firstWhere('is_base', true))->id;
    $branchRestricted = \Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch::restricted();
@endphp
<div x-data="journalEntryForm(@js(['lines' => $initialLines, 'baseCurrencyId' => $baseCurrencyId, 'currencyId' => $currency_id, 'editing' => (bool) $entryId]))"
     x-on:keydown.window.ctrl.s.prevent="submit()" x-on:keydown.window.meta.s.prevent="submit()" class="je">
    <x-accounting::validation-errors class="mb-4" />
    <div x-show="problems.length" x-cloak class="acc-alert acc-alert--warn" style="margin-bottom:1rem" role="alert">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.3 3.4c-.87 1.5.2 3.35 1.94 3.35h14.72c1.73 0 2.8-1.85 1.94-3.35L13.94 4.5c-.87-1.5-3.01-1.5-3.88 0L2.7 16.15zM12 15.75h.01"/></svg>
        <div><strong>Check the entry before saving</strong><ul><template x-for="p in problems" :key="p"><li x-text="p"></li></template></ul></div>
    </div>

    {{-- Header --}}
    <div class="acc-card" style="margin-bottom:1rem">
        <div class="acc-card-h"><div><h3>Entry details</h3><p>Who, when and why. The voucher number and the accounting period are set when the entry is posted.</p></div></div>
        <div class="acc-card-b">
            <div class="acc-grid">
                <div class="acc-field" wire:ignore>
                    <x-accounting::label for="voucher_type_id" value="Voucher type" />
                    <select id="voucher_type_id" wire:model="voucher_type_id" class="acc-select">
                        @foreach ($voucherTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) $voucher_type_id === $type->id)>{{ $type->code }} — {{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="acc-field">
                    <x-accounting::label for="entry_date" value="Entry date" />
                    <x-accounting::input id="entry_date" type="date" wire:model="entry_date" required />
                    @error('entry_date')<span class="acc-err">{{ $message }}</span>@enderror
                </div>
                <div class="acc-field" wire:ignore>
                    <x-accounting::label for="currency_id"><span>Currency<span class="req">*</span></span></x-accounting::label>
                    <select id="currency_id" wire:model="currency_id" class="acc-select" required x-on:change="currencyId = $event.target.value">
                        <option value="">Select currency</option>
                        @foreach ($currencies as $cur)
                        <option value="{{ $cur->id }}" @selected((int) $currency_id === $cur->id)>{{ $cur->code }} — {{ $cur->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="acc-field" x-show="foreign" x-cloak>
                    <x-accounting::label for="fx_rate_to_base" value="Exchange rate to base currency" />
                    <x-accounting::input id="fx_rate_to_base" type="number" step="0.00000001" class="acc-num" wire:model="fx_rate_to_base" required />
                    @error('fx_rate_to_base')<span class="acc-err">{{ $message }}</span>@enderror
                </div>
                @if(count($branches) > 0)
                <div class="acc-field" wire:ignore>
                    <x-accounting::label for="branch_id" value="Branch" />
                    <select id="branch_id" wire:model="branch_id" class="acc-select">
                        <option value="">@if($branchRestricted) Choose a branch @else Head office / not allocated @endif</option>
                        @foreach ($branches as $branch)
                        <option value="{{ $branch['id'] }}" @selected((int) $branch_id === (int) $branch['id'])>{{ $branch['code'] }} — {{ $branch['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="acc-field">
                    <x-accounting::label for="reference" value="Reference" />
                    <x-accounting::input id="reference" type="text" wire:model="reference" placeholder="Cheque no., slip no. …" />
                </div>
                <div class="acc-field acc-span">
                    <x-accounting::label for="description" value="Description" />
                    <x-accounting::input id="description" type="text" wire:model="description" placeholder="What is this entry for?" />
                </div>
            </div>

            <details class="je-source" @if($source_document_type || $source_document_number) open @endif style="margin-top:1rem">
                <summary>Link a source document <span class="acc-muted">(invoice, bill or receipt — optional)</span></summary>
                <div class="acc-grid" style="margin-top:.8rem">
                    <div class="acc-field" wire:ignore>
                        <x-accounting::label for="source_document_type" value="Document type" />
                        <select id="source_document_type" wire:model="source_document_type" class="acc-select">
                            <option value="">None</option>
                            @foreach ($documentTypes as $key => $label)
                            <option value="{{ $key }}" @selected($source_document_type === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="acc-field">
                        <x-accounting::label for="source_document_number" value="Document number" />
                        <x-accounting::input id="source_document_number" type="text" wire:model="source_document_number" placeholder="INV-1001" />
                        @error('source_document_number')<span class="acc-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="acc-field">
                        <x-accounting::label for="source_document_date" value="Document date" />
                        <x-accounting::input id="source_document_date" type="date" wire:model="source_document_date" />
                    </div>
                    <p class="acc-hint acc-span">A document can be posted only once; reverse the entry to post it again.</p>
                </div>
            </details>
        </div>
    </div>

    {{-- Lines --}}
    <div class="acc-card">
        <div class="acc-card-h">
            <div><h3>Lines</h3><p>Pick the account by its number or name. Debits must equal credits.</p></div>
            <button type="button" class="acc-btn acc-btn--sm" x-on:click="addLine()">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg> Add line
            </button>
        </div>

        {{-- One list of accounts, copied into every line (select2 then shows it with search). --}}
        <select id="je-account-proto" data-no-select2 hidden disabled aria-hidden="true">
            <option value="">Choose an account</option>
            @foreach ($accounts as $acc)
            <option value="{{ $acc->id }}" data-code="{{ $acc->account_code }}" data-name="{{ $acc->account_name }}" data-tag="{{ $acc->accountType?->name }}">{{ $acc->account_code }} — {{ $acc->account_name }}</option>
            @endforeach
        </select>
        <select id="je-cc-proto" data-no-select2 hidden disabled aria-hidden="true">
            <option value="">No cost center</option>
            @foreach ($costCenters as $cc)
            <option value="{{ $cc->id }}">{{ $cc->name }}</option>
            @endforeach
        </select>

        <div class="je-grid-wrap" wire:ignore>
            <table class="je-grid">
                <thead>
                    <tr>
                        <th style="width:2.5rem">#</th>
                        <th style="min-width:20rem">Account</th>
                        <th style="width:13rem">Cost center</th>
                        <th style="width:9.5rem;text-align:right">Debit</th>
                        <th style="width:9.5rem;text-align:right">Credit</th>
                        <th style="min-width:11rem">Note</th>
                        <th style="width:4.5rem"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, i) in lines" :key="line.uid">
                        <tr :class="{ 'is-bad': line.bad }">
                            <td class="je-n" x-text="i + 1"></td>
                            <td>
                                <select class="acc-select" data-search="always" x-model="line.chart_of_account_id" x-init="$el.innerHTML = document.getElementById('je-account-proto').innerHTML; $el.value = line.chart_of_account_id" x-effect="window.accSelect2 && window.accSelect2.sync($el, line.chart_of_account_id)" aria-label="Account"></select>
                            </td>
                            <td>
                                <select class="acc-select" x-model="line.cost_center_id" x-init="$el.innerHTML = document.getElementById('je-cc-proto').innerHTML; $el.value = line.cost_center_id" x-effect="window.accSelect2 && window.accSelect2.sync($el, line.cost_center_id)" aria-label="Cost center"></select>
                            </td>
                            <td><input type="text" inputmode="decimal" autocomplete="off" class="acc-input acc-num" placeholder="0.00" x-model="line.debit" x-on:input="line.debit = clean(line.debit); if (num(line.debit) > 0) line.credit = ''" x-on:focus="$el.select()" x-on:blur="line.debit = money(line.debit)" aria-label="Debit"></td>
                            <td><input type="text" inputmode="decimal" autocomplete="off" class="acc-input acc-num" placeholder="0.00" x-model="line.credit" x-on:input="line.credit = clean(line.credit); if (num(line.credit) > 0) line.debit = ''" x-on:focus="$el.select()" x-on:blur="line.credit = money(line.credit)" aria-label="Credit"></td>
                            <td><input type="text" class="acc-input" x-model="line.description" placeholder="Optional" x-on:keydown.enter.prevent="if (i === lines.length - 1) addLine(); else $el.closest('tr').nextElementSibling.querySelector('select').focus()" aria-label="Note"></td>
                            <td class="je-actions">
                                <button type="button" class="acc-btn acc-btn--ghost acc-btn--icon acc-btn--sm" title="Put the amount that balances the entry on this line" x-on:click="balanceTo(line)" x-show="Math.abs(diff) >= 0.005 || line.debit || line.credit">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m-6-3 6-3 6 3M4 9l2-4 2 4a2.8 2.8 0 0 1-4 0zm12 0 2-4 2 4a2.8 2.8 0 0 1-4 0z"/></svg>
                                </button>
                                <button type="button" class="acc-btn acc-btn--ghost acc-btn--icon acc-btn--sm" title="Remove line" x-on:click="removeLine(i)" :disabled="lines.length <= 2" :class="{ 'is-disabled': lines.length <= 2 }">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right">Totals</td>
                        <td class="acc-num" x-text="fmt(totalDebit)"></td>
                        <td class="acc-num" x-text="fmt(totalCredit)"></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Balance + save --}}
    <div class="je-bar">
        <div class="je-bar-l">
            <template x-if="balanced">
                <span class="acc-badge acc-badge--ok">Balanced — ready to save</span>
            </template>
            <template x-if="!balanced">
                <span class="acc-badge acc-badge--bad" x-text="totalDebit === 0 && totalCredit === 0 ? 'Enter the amounts' : 'Out of balance by ' + fmt(Math.abs(diff)) + (diff > 0 ? ' (credit short)' : ' (debit short)')"></span>
            </template>
            <span class="acc-muted" style="font-size:.78rem">Ctrl + S saves</span>
        </div>
        <div class="je-bar-r">
            <a href="{{ route(config('accounting.route_name_prefix', 'accounting').'.journal-entries.index') }}" class="acc-btn">Cancel</a>
            <button type="button" class="acc-btn acc-btn--primary" x-on:click="submit()" wire:loading.attr="disabled" wire:target="save" :disabled="!balanced">
                <span wire:loading.remove wire:target="save">Save draft</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>
<style>
.acc-kit .je-source>summary{cursor:pointer;font-size:.84rem;font-weight:600;color:var(--acc-accent);list-style:none}
.acc-kit .je-source>summary::-webkit-details-marker{display:none}
.acc-kit .je-source>summary::before{content:"+ ";font-weight:700}
.acc-kit .je-source[open]>summary::before{content:"− "}
.acc-kit .je-grid-wrap{overflow-x:auto}
.acc-kit .je-grid{width:100%;min-width:62rem;border-collapse:separate;border-spacing:0}
.acc-kit .je-grid thead th{padding:.6rem .6rem;background:var(--acc-surface-2);border-bottom:1px solid var(--acc-border);font-size:.7rem;font-weight:650;text-transform:uppercase;letter-spacing:.05em;color:var(--acc-muted);text-align:left}
.acc-kit .je-grid tbody td{padding:.45rem .5rem;border-bottom:1px solid var(--acc-border);vertical-align:middle}
.acc-kit .je-grid tbody tr.is-bad td{background:var(--acc-bad-soft)}
.acc-kit .je-grid tfoot td{padding:.7rem .6rem;background:var(--acc-surface-2);font-weight:650;font-size:.9rem}
.acc-kit .je-n{color:var(--acc-faint);font-size:.8rem;text-align:center}
.acc-kit .je-actions{white-space:nowrap;text-align:right}
.acc-kit .je-actions .acc-btn{border:0}
.acc-kit .je-bar{position:sticky;bottom:.75rem;z-index:15;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;margin-top:1rem;padding:.7rem 1rem;border:1px solid var(--acc-border);border-radius:var(--acc-radius);background:var(--acc-surface);box-shadow:0 8px 24px rgba(15,23,42,.12)}
.acc-kit .je-bar-l,.acc-kit .je-bar-r{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
[x-cloak]{display:none!important}
</style>
</div>

{{-- The script body starts with "const": Livewire 3 evaluates a @script body like an Alpine expression, where a bare "function name() {}" would not stay defined. --}}
@script
<script>
    const registerJournalEntryForm = () => {
        if (window.__jeFormRegistered) return;
        window.__jeFormRegistered = true;

        Alpine.data('journalEntryForm', (config) => ({
            lines: config.lines,
            baseCurrencyId: config.baseCurrencyId ? String(config.baseCurrencyId) : '',
            currencyId: config.currencyId ? String(config.currencyId) : '',
            nextUid: config.lines.length + 1,
            problems: [],
            editing: config.editing,

            get foreign() { return this.currencyId !== '' && this.baseCurrencyId !== '' && this.currencyId !== this.baseCurrencyId; },
            get totalDebit() { return this.round(this.lines.reduce((sum, line) => sum + this.num(line.debit), 0)); },
            get totalCredit() { return this.round(this.lines.reduce((sum, line) => sum + this.num(line.credit), 0)); },
            get diff() { return this.round(this.totalDebit - this.totalCredit); },
            get balanced() { return Math.abs(this.diff) < 0.005 && this.totalDebit > 0; },

            num(value) { const n = parseFloat(String(value ?? '').replace(/,/g, '')); return Number.isFinite(n) ? n : 0; },
            round(value) { return Math.round(value * 100) / 100; },
            clean(value) { return String(value ?? '').replace(/[^0-9.,]/g, ''); },
            money(value) { const n = this.num(value); return n > 0 ? n.toFixed(2) : ''; },
            fmt(value) { return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

            blankLine() { return { uid: this.nextUid++, chart_of_account_id: '', cost_center_id: '', debit: '', credit: '', description: '' }; },

            addLine() {
                const line = this.blankLine();
                // The new line starts with what is still missing, on the opposite side.
                if (this.diff > 0.004) line.credit = this.diff.toFixed(2);
                else if (this.diff < -0.004) line.debit = Math.abs(this.diff).toFixed(2);
                this.lines.push(line);
                this.$nextTick(() => {
                    const rows = this.$root.querySelectorAll('.je-grid tbody tr');
                    const select = rows[rows.length - 1]?.querySelector('select');
                    if (select && window.jQuery) window.jQuery(select).select2('open'); else select?.focus();
                });
            },
            removeLine(index) { if (this.lines.length > 2) this.lines.splice(index, 1); },
            balanceTo(line) {
                const others = this.lines.filter((l) => l !== line);
                const need = this.round(others.reduce((s, l) => s + this.num(l.debit) - this.num(l.credit), 0));
                if (need > 0) { line.credit = need.toFixed(2); line.debit = ''; } else if (need < 0) { line.debit = Math.abs(need).toFixed(2); line.credit = ''; }
            },
            check() {
                const problems = [];
                let valid = 0;
                this.lines.forEach((line, i) => {
                    const amount = this.num(line.debit) + this.num(line.credit);
                    line.bad = false;
                    if (!line.chart_of_account_id && amount === 0) return;
                    if (!line.chart_of_account_id) { problems.push('Line ' + (i + 1) + ': choose an account.'); line.bad = true; }
                    else if (amount === 0) { problems.push('Line ' + (i + 1) + ': enter a debit or a credit amount.'); line.bad = true; }
                    else valid++;
                });
                if (valid < 2 && !problems.length) problems.push('An entry needs at least two lines with an account and an amount.');
                if (!this.balanced && !problems.length) problems.push('Debits and credits must balance (difference ' + this.fmt(Math.abs(this.diff)) + ').');
                this.problems = problems;
                return problems.length === 0;
            },
            submit() {
                if (!this.check()) return;
                const lines = this.lines.filter((l) => l.chart_of_account_id).map((l) => ({
                    chart_of_account_id: l.chart_of_account_id, cost_center_id: l.cost_center_id,
                    debit: String(this.num(l.debit)), credit: String(this.num(l.credit)), description: l.description,
                }));
                this.$wire.save(lines);
            },
        }));
    };
    registerJournalEntryForm();
</script>
@endscript
