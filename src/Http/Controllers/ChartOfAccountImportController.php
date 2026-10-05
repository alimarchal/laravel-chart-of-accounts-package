<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Chart of accounts import and export, for the React and Blade screens and the API.
 *
 * Screens: upload → preview (nothing saved; the file is kept for 30 minutes) → confirm. API: one request with
 * dry_run=1 for the preview or without it to import.
 */
class ChartOfAccountImportController extends Controller
{
    public function __construct(private readonly ChartOfAccountImportService $import) {}

    public function export(string $format, AccountingReportExporter $exporter): HttpResponse
    {
        return $exporter->download($this->import->exportRows(), 'chart-of-accounts', $format, ['title' => 'Chart of Accounts']);
    }

    public function template(string $format, AccountingReportExporter $exporter): HttpResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        return $exporter->download($this->import->templateRows(), 'chart-of-accounts-template', $format);
    }

    public function show(Request $request): Response|View
    {
        $token = (string) $request->query('preview', '');
        $stash = $token !== '' ? $this->import->stashed($token) : null;

        return $this->page(['preview' => $stash === null ? null : [
            ...$stash['result'],
            'token' => $stash['result']['summary']['error'] === 0 ? $token : null,
            'mode' => $stash['mode'],
            'filename' => $stash['filename'],
        ]]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $this->validateUpload($request);

        try {
            $rows = $this->import->readUpload($data['file']);
            $result = $this->import->run($rows, false, $data['mode']);
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $token = $this->import->stash($rows, $data['mode'], $data['file']->getClientOriginalName(), $result);

        return to_route($this->routeName('chart-of-accounts.import'), ['preview' => $token]);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'string']])['token'];
        $stash = $this->import->stashed($token);

        if ($stash === null || $stash['result']['summary']['error'] > 0) {
            return to_route($this->routeName('chart-of-accounts.import'))->with('error', 'The preview has expired. Upload the file again.');
        }

        $result = $this->import->run($stash['rows'], true, $stash['mode']);
        $this->import->forget($token);

        if (! $result['committed']) {
            return to_route($this->routeName('chart-of-accounts.import'))->with('error', $result['summary']['error'] > 0
                ? 'The chart changed since the preview: upload the file again to see what is left to import.'
                : 'Nothing to import: every account is already up to date.');
        }

        return to_route($this->routeName('chart-of-accounts.index'))
            ->with('success', "Chart imported: {$result['summary']['create']} created, {$result['summary']['update']} updated.");
    }

    /**
     * API: POST /chart-of-accounts/import (multipart: file, mode, dry_run).
     */
    public function api(Request $request): JsonResponse
    {
        $data = $this->validateUpload($request, true);
        $result = $this->import->run($this->import->readUpload($data['file']), ! $data['dry_run'], $data['mode']);
        $status = $result['summary']['error'] > 0 ? 422 : ($result['committed'] ? 201 : 200);

        return response()->json([
            'message' => match (true) {
                $result['summary']['error'] > 0 => 'Some rows have errors: nothing was imported.',
                $result['committed'] => 'Chart imported.',
                default => $data['dry_run'] ? 'Preview only: nothing was saved.' : 'Nothing to import: every account is already up to date.',
            },
            'data' => $result,
        ], $status);
    }

    /**
     * @return array{file: UploadedFile, mode: string, dry_run: bool}
     */
    private function validateUpload(Request $request, bool $api = false): array
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:'.(int) config('accounting.chart_import.max_size_kb', 5120)],
            'mode' => ['nullable', 'in:upsert,create'],
            'dry_run' => [$api ? 'nullable' : 'prohibited', 'boolean'],
        ]);

        return ['file' => $data['file'], 'mode' => $data['mode'] ?? 'upsert', 'dry_run' => $request->boolean('dry_run')];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function page(array $props): Response|View
    {
        $props['columns'] = ChartOfAccountImportService::COLUMNS;

        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::chart-of-accounts.import', $props)
            : Inertia::render('accounting/chart-of-accounts/import', $props);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
