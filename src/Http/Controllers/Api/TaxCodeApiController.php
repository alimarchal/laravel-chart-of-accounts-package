<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Database\Eloquent\Model;

class TaxCodeApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return TaxCode::class;
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_tax_codes', 'code')->ignore($record?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
