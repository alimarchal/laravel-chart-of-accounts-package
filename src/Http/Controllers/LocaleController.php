<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Middleware\SetAccountingLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Remembers the language chosen on the language switcher for the rest of the session.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', Rule::in(array_keys(SetAccountingLocale::locales()))]]);
        $request->session()->put(SetAccountingLocale::SESSION_KEY, $data['locale']);

        return $request->expectsJson() ? response()->json(['data' => ['locale' => $data['locale']]]) : back();
    }
}
