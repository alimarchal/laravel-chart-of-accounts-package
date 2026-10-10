<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Concerns\RespondsForPayroll;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ContributionScheme;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollContributionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;

/**
 * Contribution schemes (EOBI, PESSI/SESSI, provident fund) and who they apply to.
 */
class PayrollSchemeController extends Controller
{
    use RespondsForPayroll;

    public function __construct(private readonly PayrollContributionService $schemes) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $rows = ContributionScheme::query()->orderBy('code')->get()->map(fn (ContributionScheme $scheme): array => $this->schemes->present($scheme))->values();

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows]);
        }

        return $this->render('schemes', [
            'schemes' => $rows, 'editing' => $request->integer('edit') ? $rows->firstWhere('id', $request->integer('edit')) : null,
            'employees' => Employee::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'accounts' => ChartOfAccount::query()->with('accountType:id,code')->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type_id'])
                ->map(fn (ChartOfAccount $account): array => ['id' => $account->id, 'account_code' => $account->account_code, 'account_name' => $account->account_name, 'type' => $account->accountType->code])->values(),
        ]);
    }

    public function show(ContributionScheme $scheme): JsonResponse
    {
        return response()->json(['data' => $this->schemes->present($scheme)]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $scheme = $this->schemes->save($this->schemes->validate($request->all()));

        return $request->expectsJson() ? response()->json(['data' => $this->schemes->present($scheme)], 201) : to_route($this->routeName('payroll.schemes.index'))->with('success', 'Scheme saved.');
    }

    public function update(Request $request, ContributionScheme $scheme): RedirectResponse|JsonResponse
    {
        $scheme = $this->schemes->save($this->schemes->validate($request->all(), $scheme), $scheme);

        return $request->expectsJson() ? response()->json(['data' => $this->schemes->present($scheme)]) : to_route($this->routeName('payroll.schemes.index'))->with('success', 'Scheme saved.');
    }

    public function destroy(Request $request, ContributionScheme $scheme): RedirectResponse|JsonResponse
    {
        $this->schemes->delete($scheme);

        return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Scheme deleted.');
    }

    public function assign(Request $request, ContributionScheme $scheme): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $scheme) {
            $result = $this->schemes->assign($scheme, $request->all());

            return $request->expectsJson() ? response()->json(['message' => $result['changed'].' employees changed.', 'data' => $result]) : back()->with('success', $result['changed'].' employees changed.');
        });
    }
}
