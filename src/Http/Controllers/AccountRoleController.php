<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which account plays which part for the modules (cash, bank, salary expense, receivables in the ageing …) in the current
 * company: one screen (React or Blade) and one API serve the same list.
 */
class AccountRoleController extends Controller
{
    public function index(Request $request): Response|View|JsonResponse
    {
        $rows = AccountRoles::overview();

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows, 'chart' => AccountRoles::preset(), 'presets' => AccountRoles::presets()]);
        }

        $props = [
            'roles' => $rows,
            'chart' => AccountRoles::preset(),
            'presets' => AccountRoles::presets(),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name'])->all(),
        ];

        return config('accounting.ui_driver') === 'blade' ? view('accounting::account-roles.index', $props) : Inertia::render('accounting/account-roles/index', $props);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        // A form sends an empty value for "none".
        $request->merge(['roles' => collect((array) $request->input('roles'))->map(fn ($ids) => array_values(array_filter((array) $ids, fn ($id) => $id !== '' && $id !== null && $id !== '0' && $id !== 0)))->all()]);
        $data = $request->validate(['roles' => ['required', 'array', 'min:1'], 'roles.*' => ['nullable', 'array'], 'roles.*.*' => ['integer']]);
        $registry = AccountRoles::registry();
        $before = [];
        $after = [];

        foreach ($data['roles'] as $role => $ids) {
            $role = (string) $role;
            $ids = array_values(array_unique(array_map('intval', (array) $ids)));

            if (! isset($registry[$role])) {
                throw ValidationException::withMessages(["roles.{$role}" => "'{$role}' is not a role."]);
            }

            if (! $registry[$role]['multiple'] && count($ids) > 1) {
                throw ValidationException::withMessages(["roles.{$role}" => 'This role takes one account.']);
            }

            $configured = $registry[$role]['config'] === null ? null : config($registry[$role]['config']);

            if ($configured !== null && $configured !== '' && $configured !== []) {
                throw ValidationException::withMessages(["roles.{$role}" => "This role is fixed by the configuration ({$registry[$role]['config']}); remove it there to choose the account here."]);
            }

            $valid = ChartOfAccount::query()->whereIn('id', $ids)->where('is_group', false)->where('is_active', true)->pluck('account_code', 'id');

            if ($valid->count() !== count($ids)) {
                throw ValidationException::withMessages(["roles.{$role}" => 'Choose active posting accounts of this company.']);
            }

            $before[$role] = AccountRoles::codes($role);
            $after[$role] = collect($ids)->map(fn (int $id) => $valid[$id])->all();
        }

        foreach ($data['roles'] as $role => $ids) {
            AccountRoles::set((string) $role, array_map('intval', (array) $ids));
        }

        AccountingAuditLog::record(app(CurrentCompany::class)->get(), 'ACCOUNT_ROLES_UPDATED', $before, $after);

        return $request->expectsJson()
            ? response()->json(['message' => 'Account roles saved.', 'data' => AccountRoles::overview()])
            : back()->with('success', 'Account roles saved.');
    }
}
