<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Web UI: switch the company the session works in (React and Blade).
 */
class CompanySwitchController extends Controller
{
    public function __invoke(Request $request, CurrentCompany $companies): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'integer']]);
        $company = Company::query()->findOrFail($data['company_id']);

        abort_unless($companies->canAccess($request->user(), $company), 403, 'You do not have access to this company.');

        $request->session()->put(CurrentCompany::SESSION_KEY, $company->getKey());
        $companies->forget();

        // Records shown on the previous page belong to the old company: go to the dashboard.
        return redirect()->route(config('accounting.route_name_prefix', 'accounting').'.dashboard')
            ->with('success', "Now working in {$company->name}.");
    }
}
