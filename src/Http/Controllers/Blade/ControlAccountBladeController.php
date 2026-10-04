<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ControlAccountController;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ControlAccountService;
use Alimarchal\LaravelChartOfAccounts\Support\ControlAccounts;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Control accounts (Blade). Recommended setup and marking reuse the React controller's redirects.
 */
class ControlAccountBladeController extends ControlAccountController
{
    public function page(Request $request, ControlAccountService $controls): View
    {
        $selected = $request->filled('account') ? ChartOfAccount::query()->whereNotNull('control_type')->find($request->integer('account')) : null;

        return view('accounting::control-accounts.index', [
            'controlAccounts' => $controls->overview(),
            'types' => ControlAccounts::types(),
            'recommended' => (array) config('accounting.control_accounts.recommended', []),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name', 'control_type']),
            'selected' => $selected,
            'manualPostings' => $selected ? $controls->manualPostings($selected) : [],
        ]);
    }
}
