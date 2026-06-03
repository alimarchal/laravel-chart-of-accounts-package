<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Illuminate\View\View;
use Livewire\Component;

class IncomeStatementLivewire extends Component
{
    public function render(IncomeStatementReport $report): View
    {
        return view('accounting::livewire.reports.income-statement', [
            'rows' => $report->rows(),
        ]);
    }
}
