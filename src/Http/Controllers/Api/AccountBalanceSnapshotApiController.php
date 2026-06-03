<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Illuminate\Routing\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountBalanceSnapshotApiController extends Controller
{
    public function index(): ResourceCollection
    {
        return JsonResource::collection(
            QueryBuilder::for(AccountBalanceSnapshot::query())
                ->allowedFilters(...[
                    AllowedFilter::exact('chart_of_account_id'),
                    AllowedFilter::exact('accounting_period_id'),
                ])
                ->defaultSort('-snapshot_date')
                ->paginate()
                ->withQueryString()
        );
    }

    public function show(AccountBalanceSnapshot $record): JsonResource
    {
        return JsonResource::make($record);
    }
}
