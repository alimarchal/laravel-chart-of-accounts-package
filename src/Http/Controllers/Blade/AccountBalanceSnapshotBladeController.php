<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountBalanceSnapshotBladeController extends Controller
{
    public function index(Request $request): View
    {
        $snapshots = QueryBuilder::for(AccountBalanceSnapshot::query()->with(['account', 'period']))
            ->allowedFilters(
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('period_id'),
            )
            ->defaultSort('-snapshot_date')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::account-balance-snapshots.index', [
            'snapshots' => $snapshots,
            'periods'   => AccountingPeriod::orderBy('start_date', 'desc')->get(['id', 'name']),
            'accounts'  => ChartOfAccount::where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    public function show(AccountBalanceSnapshot $record): View
    {
        return view('accounting::account-balance-snapshots.show', [
            'snapshot' => $record->load(['account', 'period']),
        ]);
    }
}
