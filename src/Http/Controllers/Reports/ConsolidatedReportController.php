<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\ConsolidatedReport;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidatedReportController extends Controller
{
    public function __invoke(Request $request, ConsolidatedReport $consolidated): Response
    {
        $filters = $request->validate([
            'report' => ['nullable', 'in:'.implode(',', ConsolidatedReport::REPORTS)],
            'companies' => ['nullable', 'string'],
            'as_of_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'include_zero' => ['nullable', 'boolean'],
        ]);
        $report = $filters['report'] ?? 'trial-balance';
        $filters['include_zero'] = $request->boolean('include_zero');
        $companies = app(CurrentCompany::class)->select($request->user(), $filters['companies'] ?? null);

        return Inertia::render('accounting/reports/consolidated', [
            'result' => $consolidated->build($report, $companies, $filters),
            'filters' => [...$filters, 'report' => $report],
        ]);
    }
}
