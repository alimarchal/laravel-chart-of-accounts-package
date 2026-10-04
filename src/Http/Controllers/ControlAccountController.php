<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Services\ControlAccountService;
use Alimarchal\LaravelChartOfAccounts\Support\ControlAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Control accounts (React).
 */
class ControlAccountController extends Controller
{
    public function index(Request $request, ControlAccountService $controls): Response
    {
        $selected = $request->filled('account') ? ChartOfAccount::query()->whereNotNull('control_type')->find($request->integer('account')) : null;

        return Inertia::render('accounting/control-accounts/index', [
            'controlAccounts' => $controls->overview(),
            'types' => ControlAccounts::types(),
            'recommended' => (array) config('accounting.control_accounts.recommended', []),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name', 'control_type']),
            'selected' => $selected ? [
                'account' => $selected->only(['id', 'account_code', 'account_name', 'control_type']),
                'manualPostings' => $controls->manualPostings($selected),
            ] : null,
        ]);
    }

    public function recommended(ChartOfAccountService $accounts): RedirectResponse
    {
        try {
            $marked = $accounts->applyRecommendedControlAccounts();
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $marked === []
            ? 'Nothing to mark: the recommended accounts are already control accounts or do not exist.'
            : 'Marked as control accounts: '.collect($marked)->map(fn ($row) => "{$row['account_code']} {$row['account_name']}")->implode(', ').'.');
    }

    public function setType(Request $request, ChartOfAccount $chartOfAccount, ChartOfAccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['control_type' => ['nullable', 'string', Rule::in(array_keys(ControlAccounts::types()))]]);

        try {
            $account = $accounts->setControlType($chartOfAccount, $data['control_type'] ?? null);
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $account->control_type
            ? "{$account->account_code} {$account->account_name} is now the control account for ".mb_strtolower((string) ControlAccounts::label($account->control_type)).'.'
            : "{$account->account_code} {$account->account_name} is no longer a control account.");
    }
}
