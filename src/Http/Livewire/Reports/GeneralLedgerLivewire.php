<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Reports\GeneralLedgerReport;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GeneralLedgerLivewire extends Component
{
    use WithPagination;

    public string $date_from = '';

    public string $date_to = '';

    public ?int $account_id = null;

    public string $status = '';

    public function mount(): void
    {
        $this->date_from = now()->startOfYear()->format('Y-m-d');
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

    public function updatedAccountId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(GeneralLedgerReport $report): View
    {
        $filters = [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'account_id' => $this->account_id,
            'status' => $this->status,
        ];

        return view('accounting::livewire.reports.general-ledger', [
            'rows' => $report->query($filters)->paginate(50),
            'totals' => $report->totals($filters),
            'accounts' => ChartOfAccount::where('is_active', true)->orderBy('account_code')->get(),
        ]);
    }
}
