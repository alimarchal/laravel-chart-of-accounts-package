<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingPeriodService;
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
            'status' => ['sometimes', Rule::in(['open', 'closed', 'archived'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistCreate(array $data): Model
    {
        return app(AccountingPeriodService::class)->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistUpdate(Model $record, array $data): Model
    {
        /** @var AccountingPeriod $record */
        return app(AccountingPeriodService::class)->update($record, $data);
    }

    protected function persistDelete(Model $record): void
    {
        /** @var AccountingPeriod $record */
        app(AccountingPeriodService::class)->delete($record);
    }
}
