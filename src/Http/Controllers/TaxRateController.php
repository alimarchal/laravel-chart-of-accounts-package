<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Illuminate\Database\Eloquent\Model;

class TaxRateController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return TaxRate::class;
    }

    protected function routeName(): string
    {
        return 'tax-rates';
    }

    protected function title(): string
    {
        return 'Tax Rate';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'tax_code_id', 'label' => 'Tax Code', 'type' => 'select', 'options' => TaxCode::query()->orderBy('code')->pluck('code', 'id')->all(), 'table' => true, 'filter' => true],
            ['name' => 'rate', 'label' => 'Rate %', 'type' => 'number', 'step' => '0.0001', 'table' => true],
            ['name' => 'effective_from', 'label' => 'Effective From', 'type' => 'date', 'table' => true],
            ['name' => 'effective_to', 'label' => 'Effective To', 'type' => 'date', 'table' => true],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'table' => true, 'filter' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'tax_code_id' => ['required', 'exists:accounting_tax_codes,id'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
