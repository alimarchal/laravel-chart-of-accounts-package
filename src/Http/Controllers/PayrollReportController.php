<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollReportService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollTaxReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Payroll reports (month comparison, cost centers, headcount) and the tax reports (an employee's certificate, the annual statement).
 */
class PayrollReportController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollReportService $reports, private readonly PayrollTaxReportService $tax, private readonly AccountingReportExporter $exporter) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'from' => ['nullable', 'date_format:Y-m'], 'to' => ['nullable', 'date_format:Y-m']]);
        $year = (int) ($data['year'] ?? now()->format('Y'));
        $from = $data['from'] ?? $year.'-01';
        $to = $data['to'] ?? $year.'-12';
        $payload = ['year' => $year, 'from' => $from, 'to' => $to, 'comparison' => $this->reports->comparison($year), 'cost_centers' => $this->reports->costCenters($from, $to), 'headcount' => $this->reports->headcount($year)];

        return $request->expectsJson() ? response()->json(['data' => $payload]) : $this->render('reports', $payload);
    }

    public function export(Request $request, string $report, string $format): BaseResponse
    {
        abort_unless(in_array($report, ['comparison', 'cost-centers', 'headcount'], true), 404);
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'from' => ['nullable', 'date_format:Y-m'], 'to' => ['nullable', 'date_format:Y-m']]);
        $year = (int) ($data['year'] ?? now()->format('Y'));
        $rows = match ($report) {
            'comparison' => $this->reports->comparison($year),
            'cost-centers' => $this->reports->costCenters($data['from'] ?? $year.'-01', $data['to'] ?? $year.'-12'),
            default => $this->reports->headcount($year),
        };

        abort_if($rows === [], 422, 'There is nothing to export for those dates.');

        return $this->exporter->download($rows, 'payroll-'.$report.'-'.$year, $format, ['title' => 'Payroll '.str_replace('-', ' ', $report)]);
    }

    public function tax(Request $request): Response|View|JsonResponse
    {
        $year = $this->taxYear($request);
        $payload = ['tax_year' => $year, 'label' => $this->tax->taxYear($year)['label'], 'rows' => $this->tax->annual($year)];

        return $request->expectsJson()
            ? response()->json(['data' => $payload])
            : $this->render('tax', [...$payload, 'employees' => Employee::query()->orderBy('code')->get(['id', 'code', 'name'])]);
    }

    public function taxExport(Request $request, string $format): BaseResponse
    {
        $year = $this->taxYear($request);
        $rows = $this->tax->annual($year);
        abort_if($rows === [], 422, 'No salary was paid in that tax year.');

        return $this->exporter->download($rows, 'salary-tax-statement-'.$year, $format, ['title' => 'Salary and tax withheld, '.$this->tax->taxYear($year)['label']]);
    }

    public function certificate(Request $request, Employee $employee): Response|View|JsonResponse
    {
        $certificate = $this->tax->certificate($employee, $this->taxYear($request));

        return $request->expectsJson() ? response()->json(['data' => $certificate]) : $this->render('tax-certificate', ['certificate' => $certificate]);
    }

    private function taxYear(Request $request): int
    {
        $start = max(1, min(12, (int) config('accounting.payroll.tax_year_start_month', 7)));
        $default = (int) now()->format('n') >= $start ? (int) now()->format('Y') : (int) now()->format('Y') - 1;

        return (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? $default);
    }
}
