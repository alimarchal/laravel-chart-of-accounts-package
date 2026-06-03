<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Http\Resources\AccountResource;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChartOfAccountApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AccountResource::collection(
            QueryBuilder::for(ChartOfAccount::query()->with(['accountType', 'currency']))
                ->allowedFilters(...[
                    AllowedFilter::partial('account_code'),
                    AllowedFilter::partial('account_name'),
                    AllowedFilter::exact('account_type_id'),
                    AllowedFilter::exact('currency_id'),
                    AllowedFilter::exact('is_group'),
                    AllowedFilter::exact('is_active'),
                ])
                ->orderBy('account_code')
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(Request $request): AccountResource
    {
        $account = ChartOfAccount::query()->create($this->validated($request));

        return AccountResource::make($account->load(['accountType', 'currency']));
    }

    public function show(ChartOfAccount $chartOfAccount): AccountResource
    {
        return AccountResource::make($chartOfAccount->load(['accountType', 'currency', 'parent']));
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): AccountResource
    {
        $chartOfAccount->update($this->validated($request, $chartOfAccount));

        return AccountResource::make($chartOfAccount->refresh()->load(['accountType', 'currency', 'parent']));
    }

    public function destroy(ChartOfAccount $chartOfAccount): JsonResponse
    {
        abort_if($chartOfAccount->is_system, 422, 'System accounts cannot be deleted.');

        $chartOfAccount->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ChartOfAccount $record = null): array
    {
        $request->merge([
            'is_group' => $request->boolean('is_group'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return $request->validate([
            'parent_id' => ['nullable', 'exists:accounting_chart_of_accounts,id'],
            'account_type_id' => ['required', 'exists:accounting_account_types,id'],
            'currency_id' => ['required', 'exists:accounting_currencies,id'],
            'account_code' => ['required', 'string', 'max:30', Rule::unique('accounting_chart_of_accounts', 'account_code')->ignore($record?->id)],
            'account_name' => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'description' => ['nullable', 'string'],
            'is_group' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
