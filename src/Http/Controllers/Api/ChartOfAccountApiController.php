<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Http\Resources\AccountResource;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChartOfAccountApiController extends Controller
{
    public function __construct(private readonly ChartOfAccountService $service) {}

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
        $account = $this->service->create($this->service->validated($request));

        return AccountResource::make($account->load(['accountType', 'currency']));
    }

    public function show(ChartOfAccount $chartOfAccount): AccountResource
    {
        return AccountResource::make($chartOfAccount->load(['accountType', 'currency', 'parent']));
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): AccountResource
    {
        $account = $this->service->update($chartOfAccount, $this->service->validated($request, $chartOfAccount));

        return AccountResource::make($account->load(['accountType', 'currency', 'parent']));
    }

    public function destroy(ChartOfAccount $chartOfAccount): JsonResponse
    {
        $this->service->delete($chartOfAccount);

        return response()->json(null, 204);
    }
}
