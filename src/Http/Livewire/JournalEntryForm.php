<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Illuminate\View\View;
use Livewire\Component;

class JournalEntryForm extends Component
{
    public ?int $entryId = null;

    public string $entry_date = '';

    public ?int $accounting_period_id = null;

    public ?int $currency_id = null;

    public float $fx_rate_to_base = 1;

    public string $reference = '';

    public string $description = '';

    /** @var array<int, array{chart_of_account_id: int|null, cost_center_id: int|null, debit: string, credit: string, description: string}> */
    public array $lines = [];

    public function mount(?JournalEntry $entry = null): void
    {
        if ($entry && $entry->exists) {
            $this->entryId = $entry->id;
            $this->entry_date = $entry->entry_date->format('Y-m-d');
            $this->accounting_period_id = $entry->accounting_period_id;
            $this->currency_id = $entry->currency_id;
            $this->fx_rate_to_base = (float) $entry->fx_rate_to_base;
            $this->reference = $entry->reference ?? '';
            $this->description = $entry->description ?? '';
            $this->lines = $entry->lines->map(fn ($l) => [
                'chart_of_account_id' => $l->chart_of_account_id,
                'cost_center_id' => $l->cost_center_id,
                'debit' => $l->debit,
                'credit' => $l->credit,
                'description' => $l->description ?? '',
            ])->toArray();
        } else {
            $this->entry_date = now()->format('Y-m-d');
            $this->addLine();
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'chart_of_account_id' => null,
            'cost_center_id' => null,
            'debit' => '0.00',
            'credit' => '0.00',
            'description' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 2) {
            array_splice($this->lines, $index, 1);
        }
    }

    public function totalDebits(): float
    {
        return collect($this->lines)->sum(fn ($l) => (float) ($l['debit'] ?? 0));
    }

    public function totalCredits(): float
    {
        return collect($this->lines)->sum(fn ($l) => (float) ($l['credit'] ?? 0));
    }

    public function save(JournalEntryService $service): void
    {
        $this->validate([
            'entry_date' => ['required', 'date'],
            'accounting_period_id' => ['required', 'exists:accounting_periods,id'],
            'currency_id' => ['required', 'exists:accounting_currencies,id'],
            'fx_rate_to_base' => ['required', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'exists:accounting_chart_of_accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        if (abs($this->totalDebits() - $this->totalCredits()) >= 0.01) {
            $this->addError('lines', 'Debits and credits must balance before saving. Current difference: '.number_format(abs($this->totalDebits() - $this->totalCredits()), 2));

            return;
        }

        if ($this->totalDebits() <= 0) {
            $this->addError('lines', 'Journal entry must have at least one non-zero debit and credit line.');

            return;
        }

        $data = [
            'entry_date' => $this->entry_date,
            'accounting_period_id' => $this->accounting_period_id,
            'currency_id' => $this->currency_id,
            'fx_rate_to_base' => $this->fx_rate_to_base,
            'reference' => $this->reference,
            'description' => $this->description,
            'lines' => $this->lines,
        ];

        if ($this->entryId) {
            $entry = JournalEntry::findOrFail($this->entryId);
            $service->updateDraft($entry, $data);
            session()->flash('success', 'Journal entry updated.');
        } else {
            $entry = $service->create($data);
            session()->flash('success', 'Journal entry created.');
        }

        $this->redirect(route('accounting.journal-entries.show', $entry));
    }

    public function render(): View
    {
        return view('accounting::livewire.journal-entry-form', [
            'periods' => AccountingPeriod::orderBy('start_date', 'desc')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'accounts' => ChartOfAccount::where('is_active', true)->where('is_group', false)->orderBy('account_code')->get(),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
