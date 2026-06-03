<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CostCenterApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return CostCenter::class;
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('accounting_cost_centers', 'code')->ignore($record?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['cost_center', 'project'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
