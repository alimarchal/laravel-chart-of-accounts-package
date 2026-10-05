<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Services\ChartTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Industry chart templates: choose one, see what it would add, add it (React, Blade and API).
 */
class ChartTemplateController extends Controller
{
    public function __construct(private readonly ChartTemplateService $templates) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => $this->templates->available()]);
        }

        $key = (string) $request->query('template', '');
        $props = [
            'templates' => $this->templates->available(),
            'preview' => null,
            'selected' => $key,
        ];

        if ($key !== '') {
            try {
                $props['preview'] = $this->templates->preview($key);
            } catch (AccountingException $exception) {
                session()->flash('error', $exception->getMessage());
            }
        }

        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::chart-of-accounts.templates', $props)
            : Inertia::render('accounting/chart-of-accounts/templates', $props);
    }

    public function apply(Request $request, string $template): RedirectResponse|JsonResponse
    {
        $dryRun = $request->boolean('dry_run');

        try {
            if ($request->expectsJson() && $dryRun) {
                return response()->json(['message' => 'Preview only: nothing was added.', 'data' => $this->templates->preview($template)]);
            }

            $result = $this->templates->apply($template);
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => count($result['created']).' accounts added.', 'data' => $result], $result['created'] === [] ? 200 : 201);
        }

        return to_route(config('accounting.route_name_prefix', 'accounting').'.chart-of-accounts.index')->with('success', count($result['created']) === 0
            ? 'Nothing to add: the chart already has every account of this template.'
            : count($result['created']).' accounts added from the template; '.$result['skipped'].' already existed and were left as they are.');
    }

    public function show(string $template): JsonResponse
    {
        return response()->json(['data' => $this->templates->preview($template)]);
    }
}
