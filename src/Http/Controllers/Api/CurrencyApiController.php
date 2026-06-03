<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CurrencyApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return Currency::class;
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
