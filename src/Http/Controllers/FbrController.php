<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\FbrSubmission;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Services\FbrService;
use Alimarchal\LaravelChartOfAccounts\Support\ListFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales tax invoices sent to FBR, for the React and Blade screens and the API.
 */
class FbrController extends Controller
{
    public function __construct(private readonly FbrService $fbr) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', 'in:none,pending,accepted,failed'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date']]);
        $base = ListFilters::for(PartyDocument::query(), $request, [
            ListFilters::exact('kind'),
            ListFilters::exact('party_id'),
            ListFilters::search('search', ['number', 'reference'], ['party' => ['name', 'code']]),
            ListFilters::from('date_from', 'issue_date'),
            ListFilters::to('date_to', 'issue_date'),
            ListFilters::min('min_total', 'total'),
            ListFilters::max('max_total', 'total'),
        ], sorts: ['issue_date', 'number', 'total']);
        $documents = $this->fbr->overview($filters['status'] ?? null, $base);
        $props = ['documents' => $documents, 'enabled' => $this->fbr->enabled(), 'mode' => (string) config('accounting.fbr.mode', 'fake'), 'filters' => ['status' => $filters['status'] ?? '', ...ListFilters::current($request, ['search', 'kind', 'date_from', 'date_to', 'min_total', 'max_total'])]];

        if ($request->expectsJson()) {
            return response()->json(['data' => ['enabled' => $props['enabled'], 'mode' => $props['mode'], 'documents' => $documents]]);
        }

        return config('accounting.ui_driver') === 'blade' ? view('accounting::fbr.index', $props) : Inertia::render('accounting/fbr/index', $props);
    }

    public function submit(Request $request, PartyDocument $document): RedirectResponse|JsonResponse
    {
        try {
            $submission = $this->fbr->submit($document);
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->with('error', $exception->getMessage());
        }

        return $request->expectsJson()
            ? response()->json(['data' => $this->present($submission)])
            : back()->with($submission->status === 'accepted' ? 'success' : 'error', $submission->status === 'accepted' ? "Accepted by FBR: {$submission->fbr_invoice_number}" : 'FBR refused it: '.$submission->error);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FbrSubmission $submission): array
    {
        return ['id' => $submission->id, 'document_id' => $submission->party_document_id, 'status' => $submission->status, 'mode' => $submission->mode, 'attempts' => $submission->attempts, 'fbr_invoice_number' => $submission->fbr_invoice_number, 'error' => $submission->error, 'submitted_at' => $submission->submitted_at?->toISOString()];
    }
}
