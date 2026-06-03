<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AccountTypeApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return AccountType::class;
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('accounting_account_types', 'code')->ignore($record?->getKey())],
            'name' => ['required', 'string', 'max:255', Rule::unique('accounting_account_types', 'name')->ignore($record?->getKey())],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'report_group' => ['required', Rule::in(['BalanceSheet', 'IncomeStatement'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
