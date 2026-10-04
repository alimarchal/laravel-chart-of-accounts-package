<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChartOfAccountController extends Controller
{
    public function __construct(private readonly ChartOfAccountService $service) {}

    public function index(): Response
    {
        $accounts = QueryBuilder::for(ChartOfAccount::query()->with(['accountType', 'currency', 'parent']))
            ->allowedFilters(...[
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::exact('account_type_id'),
                AllowedFilter::exact('currency_id'),
                AllowedFilter::exact('is_group'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('account_code')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('accounting/chart-of-accounts/index', [
            'accounts' => $accounts,
            'filters' => request()->input('filter', []),
            'accountTypes' => AccountType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounting/chart-of-accounts/form', $this->formPayload(
            title: 'Create Account',
            action: route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.store'),
            method: 'post'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create($this->service->validated($request));

        return to_route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.index')->with('success', 'Account created.');
    }

    public function edit(ChartOfAccount $chartOfAccount): Response
    {
        return Inertia::render('accounting/chart-of-accounts/form', $this->formPayload(
            title: 'Edit Account',
            action: route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.update', $chartOfAccount),
            method: 'put',
            record: $chartOfAccount
        ));
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->service->update($chartOfAccount, $this->service->validated($request, $chartOfAccount));

        return to_route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.index')->with('success', 'Account updated.');
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->service->delete($chartOfAccount);

        return to_route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.index')->with('success', 'Account deleted.');
    }

    public function tree(): Response
    {
        return Inertia::render('accounting/chart-of-accounts/tree', [
            'roots' => $this->service->tree(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(string $title, string $action, string $method, ?ChartOfAccount $record = null): array
    {
        return [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'record' => $record,
            'accountTypes' => AccountType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'normal_balance']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_base']),
            'parents' => ChartOfAccount::query()
                ->where('is_group', true)
                ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
        ];
    }
}
