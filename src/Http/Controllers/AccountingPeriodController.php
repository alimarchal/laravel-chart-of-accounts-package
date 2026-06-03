<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AccountingPeriodController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return AccountingPeriod::class;
    }

    protected function routeName(): string
    {
        return 'periods';
    }

    protected function title(): string
    {
        return 'Accounting Period';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'table' => true],
            ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'table' => true],
            ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'table' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['open' => 'Open', 'closed' => 'Closed', 'archived' => 'Archived'], 'table' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['open', 'closed', 'archived'])],
        ];
    }
}
