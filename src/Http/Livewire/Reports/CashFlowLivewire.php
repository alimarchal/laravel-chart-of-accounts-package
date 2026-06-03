<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\CashFlowReport;
use Illuminate\View\View;
use Livewire\Component;

class CashFlowLivewire extends Component
{
    public string $date_from = '';

    public string $date_to = '';

    public function mount(): void
    {
        $this->date_from = now()->startOfYear()->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
    }

    public function render(CashFlowReport $report): View
    {
        return view('accounting::livewire.reports.cash-flow', [
            'rows' => $report->rows(['date_from' => $this->date_from, 'date_to' => $this->date_to]),
        ]);
    }
}
