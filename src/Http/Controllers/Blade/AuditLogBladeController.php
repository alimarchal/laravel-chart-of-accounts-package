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

        $auditLogs = QueryBuilder::for($query, request())
            ->allowedFilters(
                AllowedFilter::partial('table_name'),
                // Trigger rows use insert/update/delete, application rows JOURNAL_POSTED etc.: match case-insensitively.
                AllowedFilter::callback('action', fn ($q, $action) => $q->whereRaw('LOWER(action) = ?', [strtolower((string) $action)])),
                AllowedFilter::exact('user_id'),
                AllowedFilter::callback('date_from', fn ($q, $date) => $q->whereDate('created_at', '>=', $date)),
                AllowedFilter::callback('date_to', fn ($q, $date) => $q->whereDate('created_at', '<=', $date)),
            )
            ->defaultSort('-id')
            ->paginate(25)
            ->withQueryString();

        $actions = AccountingAuditLog::query()
            ->selectRaw('DISTINCT action')
            ->orderBy('action')
            ->pluck('action');

        $tableNames = AccountingAuditLog::query()
            ->selectRaw('DISTINCT table_name')
            ->orderBy('table_name')
            ->pluck('table_name');

        return view('accounting::audit-logs.index', [
            'actions' => $actions,
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
