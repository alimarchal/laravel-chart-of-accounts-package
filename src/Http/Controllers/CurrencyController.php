<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CurrencyController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return Currency::class;
    }

    protected function routeName(): string
    {
        return 'currencies';
    }

    protected function title(): string
    {
        return 'Currency';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'ISO Code', 'type' => 'text', 'table' => true],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'table' => true],
            ['name' => 'symbol', 'label' => 'Symbol', 'type' => 'text', 'table' => true],
            ['name' => 'exchange_rate_to_base', 'label' => 'Exchange Rate To Base', 'type' => 'number', 'step' => '0.00000001', 'table' => true],
            ['name' => 'is_base', 'label' => 'Base Currency', 'type' => 'checkbox', 'table' => true],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'table' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'size:3', Rule::unique('accounting_currencies', 'code')->ignore($record?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'exchange_rate_to_base' => ['required', 'numeric', 'gt:0'],
            'is_base' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
