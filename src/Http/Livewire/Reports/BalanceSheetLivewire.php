<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Illuminate\View\View;
use Livewire\Component;

class BalanceSheetLivewire extends Component
{
    public function render(BalanceSheetReport $report): View
    {
        return view('accounting::livewire.reports.balance-sheet', [
            'rows' => $report->rows(),
        ]);
    }
}
