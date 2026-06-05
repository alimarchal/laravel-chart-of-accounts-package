<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AuditLogBladeController extends Controller
{
    public function index(Request $request): View
    {
        $query = AccountingAuditLog::query()->with('user');

        if ($dateFrom = $request->input('filter.date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('filter.date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $auditLogs = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::partial('table_name'),
                AllowedFilter::exact('action'),
                AllowedFilter::partial('user_id'),
            )
            ->defaultSort('-id')
            ->paginate(25)
            ->withQueryString();

        $tableNames = AccountingAuditLog::query()
            ->selectRaw('DISTINCT table_name')
            ->orderBy('table_name')
            ->pluck('table_name');

        return view('accounting::audit-logs.index', [
            'auditLogs' => $auditLogs,
            'tableNames' => $tableNames,
        ]);
    }

    public function show(AccountingAuditLog $record): View
    {
        return view('accounting::audit-logs.show', [
            'auditLog' => $record,
        ]);
    }
}
