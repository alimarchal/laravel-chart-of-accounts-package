<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PeriodCloseController;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Services\PeriodCloseChecklist;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Blade version of the close workspace; closing, year-end close, reopening and monthly generation
 * are shared with the React controller.
 */
class PeriodCloseBladeController extends PeriodCloseController
{
    public function workspace(Request $request, AccountingPeriod $period, PeriodCloseChecklist $checklist): View
    {
        return view('accounting::accounting-periods.close', [
            'checklist' => $checklist->build($period, $request->boolean('year_end')),
            'isFiscalYearEnd' => self::isFiscalYearEnd($period, app(CurrentCompany::class)->get()->fiscal_year_start_month ?: 1),
        ]);
    }
}
