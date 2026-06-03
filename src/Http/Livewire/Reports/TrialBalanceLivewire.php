<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Illuminate\View\View;
use Livewire\Component;

class TrialBalanceLivewire extends Component
{
    public function render(TrialBalanceReport $report): View
    {
        return view('accounting::livewire.reports.trial-balance', [
            'rows' => $report->rows(),
            'totals' => $report->totals(),
        ]);
    }
}
