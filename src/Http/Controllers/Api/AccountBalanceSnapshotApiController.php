<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\Concerns\ResolvesPerPage;
use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountBalanceSnapshotApiController extends Controller
{
    use ResolvesPerPage;

    public function index(): ResourceCollection
    {
        return JsonResource::collection(
            QueryBuilder::for(AccountBalanceSnapshot::query(), request())
                ->allowedFilters(...[
                    AllowedFilter::exact('chart_of_account_id'),
                    AllowedFilter::exact('accounting_period_id'),
                ])
                ->defaultSort('-snapshot_date')
                ->paginate($this->perPage())
                ->withQueryString()
        );
    }

    public function show(AccountBalanceSnapshot $record): JsonResource
    {
        return JsonResource::make($record);
    }
}
