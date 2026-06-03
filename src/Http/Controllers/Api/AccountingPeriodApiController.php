<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AccountingPeriodApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return AccountingPeriod::class;
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
