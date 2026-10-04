<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\ReportExport;
use Alimarchal\LaravelChartOfAccounts\Reports\ReportCatalog;
use Alimarchal\LaravelChartOfAccounts\Services\ReportExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "My exports": large report exports prepared in the background. Users only ever see their own.
 */
class ReportExportListController extends Controller
{
    public function __construct(private readonly ReportExportService $exports) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $rows = ReportExport::query()->where('user_id', $request->user()?->getAuthIdentifier())->latest('id')->limit(50)->get()
            ->map(fn (ReportExport $export) => $this->exports->present($export))->values();

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows]);
        }

        if (config('accounting.ui_driver') === 'blade') {
            return view('accounting::exports.index', ['exports' => $rows]);
        }

        return Inertia::render('accounting/exports/index', ['exports' => $rows]);
    }

    /**
     * Queue an export explicitly (API): POST /reports/{report}/exports/{format} with the report's filters.
     */
    public function store(Request $request, string $report, string $format, ReportCatalog $catalog): JsonResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $definition = $catalog->resolve($report, $request->all());
        abort_if($definition === null, 404);
        abort_unless($request->user()?->can($definition['permission']), 403);

        $export = $this->exports->queue($request->user(), $report, $format, $request->all());

        return response()->json(['data' => $this->exports->present($export->refresh())], 202);
    }

    public function download(Request $request, ReportExport $export): StreamedResponse
    {
        $this->authorizeOwner($request, $export);

        return $this->exports->download($export);
    }

    public function destroy(Request $request, ReportExport $export): RedirectResponse|JsonResponse
    {
        $this->authorizeOwner($request, $export);
        $this->exports->delete($export);

        return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Export deleted.');
    }

    private function authorizeOwner(Request $request, ReportExport $export): void
    {
        abort_unless((string) $export->user_id === (string) $request->user()?->getAuthIdentifier(), 404);
    }
}
