<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AccountTypeController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return AccountType::class;
    }

    protected function routeName(): string
    {
        return 'account-types';
    }

    protected function title(): string
    {
        return 'Account Type';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'table' => true],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'table' => true],
            ['name' => 'normal_balance', 'label' => 'Normal Balance', 'type' => 'select', 'options' => ['debit' => 'Debit', 'credit' => 'Credit'], 'table' => true],
            ['name' => 'report_group', 'label' => 'Report Group', 'type' => 'select', 'options' => ['BalanceSheet' => 'Balance Sheet', 'IncomeStatement' => 'Income Statement'], 'table' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'table' => true],
        ];
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
