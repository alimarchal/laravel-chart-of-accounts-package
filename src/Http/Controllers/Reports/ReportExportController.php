<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\ReportCatalog;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $report, string $format, AccountingReportExporter $exporter, ReportCatalog $catalog, ReportExportService $exports): Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $definition = $catalog->resolve($report, $request->query());
        abort_if($definition === null, 404);

        // Rows are resolved lazily so nothing is queried before the permission check.
        abort_unless($request->user()?->can($definition['permission']), 403);

        $rows = ($definition['rows'])();

        // Too large to build in this request: generate it in the background and list it under Exports.
        if ($format !== 'csv' && $exports->shouldQueue($rows, $format)) {
            $export = $exports->queue($request->user(), $report, $format, $request->query());

            return $request->expectsJson()
                ? response()->json(['data' => $exports->present($export), 'message' => 'The export is large and is being prepared in the background.'], 202)
                : redirect()->to($exports->listUrl())->with('success', 'The export is large and is being prepared in the background. It will appear here when ready.');
        }

        return $exporter->download($rows, $report, $format, ['title' => $definition['title'], 'filters' => $definition['filters']]);
    }
}
