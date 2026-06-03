<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\CashBookReport;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CashBookLivewire extends Component
{
    use WithPagination;

    public string $date_from = '';

    public string $date_to = '';

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

    public function render(CashBookReport $report): View
    {
        $filters = [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ];

        return view('accounting::livewire.reports.cash-book', [
            'rows' => $report->query($filters)->paginate(50),
            'totals' => $report->totals($filters),
        ]);
    }
}
