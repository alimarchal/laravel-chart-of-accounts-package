<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AgedPayablesReport;
use Illuminate\View\View;
use Livewire\Component;

class AgedPayablesLivewire extends Component
{
    public string $asOfDate = '';

    public function mount(): void
    {
        $this->asOfDate = now()->toDateString();
    }

    public function render(AgedPayablesReport $report): View
    {
        return view('accounting::livewire.reports.aged-payables', [
            'rows' => $report->rows($this->asOfDate ?: now()->toDateString()),
        ]);
    }
}
