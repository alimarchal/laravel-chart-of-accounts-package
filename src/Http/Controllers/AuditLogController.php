<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return AccountingAuditLog::class;
    }

    protected function routeName(): string
    {
        return 'audit-logs';
    }

    protected function title(): string
    {
        return 'Audit Log';
    }

    protected function readOnly(): bool
    {
        return true;
    }

    protected function fields(): array
    {
        return [
            ['name' => 'table_name', 'label' => 'Table', 'type' => 'text', 'table' => true],
            ['name' => 'record_id', 'label' => 'Record ID', 'type' => 'text', 'table' => true],
            ['name' => 'action', 'label' => 'Action', 'type' => 'text', 'table' => true],
            ['name' => 'user_id', 'label' => 'User ID', 'type' => 'number', 'table' => true],
            ['name' => 'ip_address', 'label' => 'IP Address', 'type' => 'text', 'table' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [];
    }
}
