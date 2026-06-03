<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CostCenterController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return CostCenter::class;
    }

    protected function routeName(): string
    {
        return 'cost-centers';
    }

    protected function title(): string
    {
        return 'Cost Center';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'table' => true],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'table' => true],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['cost_center' => 'Cost Center', 'project' => 'Project'], 'table' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'table' => true],
        ];
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
