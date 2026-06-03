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
        $auditLogs = QueryBuilder::for(AccountingAuditLog::query())
            ->allowedFilters(
                AllowedFilter::partial('table_name'),
                AllowedFilter::exact('action'),
            )
            ->defaultSort('-id')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::audit-logs.index', [
            'auditLogs' => $auditLogs,
        ]);
    }

    public function show(AccountingAuditLog $record): View
    {
        return view('accounting::audit-logs.show', [
            'auditLog' => $record,
        ]);
    }
}
