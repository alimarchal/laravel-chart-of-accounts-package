<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;

class TaxRateApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return TaxRate::class;
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

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('tax_code_id'),
            AllowedFilter::exact('is_active'),
        ];
    }
}
