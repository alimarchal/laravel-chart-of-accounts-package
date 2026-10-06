<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

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
            'kind' => ['sometimes', Rule::in(TaxCode::KINDS)],
            'tax_account_id' => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false))],
            'jurisdiction' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
