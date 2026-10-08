<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollBankFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * The bank salary file of a posted payroll run (CSV or Excel), or a preview of it as JSON.
 */
class PayrollBankFileController extends Controller
{
    public function __construct(private readonly PayrollBankFileService $bankFile, private readonly AccountingReportExporter $exporter) {}

    public function show(Request $request, PayrollRun $run, string $format): Response|JsonResponse
    {
        $layout = (string) $request->query('layout', 'standard');
        $file = $this->bankFile->build($run, $layout);

        if ($request->boolean('preview')) {
            return response()->json(['data' => $file['rows'], 'missing' => $file['missing'], 'total' => $file['total'], 'count' => $file['count'], 'layouts' => $this->bankFile->layouts()]);
        }

        abort_if($file['rows'] === [], 422, 'There is nobody to pay through the bank: no net pay, or no bank accounts.');

        return $this->exporter->download($file['rows'], 'salary-bank-file-'.Carbon::parse($run->period_month)->format('Y-m').'-'.$layout, $format);
    }
}
