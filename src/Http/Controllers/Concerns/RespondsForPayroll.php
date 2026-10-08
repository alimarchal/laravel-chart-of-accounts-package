<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the payroll controllers share: one action serves the React screens, the Blade screens and the API.
 */
trait RespondsForPayroll
{
    /**
     * @param  \Closure(): (RedirectResponse|JsonResponse)  $action
     */
    private function guard(Request $request, \Closure $action): RedirectResponse|JsonResponse
    {
        try {
            return $action();
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::payroll.'.$page, $props)
            : Inertia::render('accounting/payroll/'.$page, $props);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
