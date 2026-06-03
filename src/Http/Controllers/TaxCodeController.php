<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TaxCodeController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return TaxCode::class;
    }

    protected function routeName(): string
    {
        return 'tax-codes';
    }

    protected function title(): string
    {
        return 'Tax Code';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'table' => true, 'filter' => true],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'table' => true, 'filter' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'table' => true, 'filter' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('accounting_tax_codes', 'code')->ignore($record?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
