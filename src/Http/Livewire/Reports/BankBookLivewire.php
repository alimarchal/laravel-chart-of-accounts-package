<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Reports\BankBookReport;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BankBookLivewire extends Component
{
    use WithPagination;

    public string $date_from = '';

    public string $date_to = '';

    public ?int $bank_account_id = null;

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedBankAccountId(): void
    {
        $this->resetPage();
    }

    public function render(BankBookReport $report): View
    {
        $filters = [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'bank_account_id' => $this->bank_account_id,
        ];

        return view('accounting::livewire.reports.bank-book', [
            'rows' => $report->query($filters)->paginate(50),
            'totals' => $report->totals($filters),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('account_name')->get(),
        ]);
    }
}
