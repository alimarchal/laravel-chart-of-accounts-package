<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Database\Eloquent\Model;

class BankAccountController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return BankAccount::class;
    }

    protected function routeName(): string
    {
        return 'bank-accounts';
    }

    protected function title(): string
    {
        return 'Bank Account';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'chart_of_account_id', 'label' => 'Ledger Account', 'type' => 'select', 'options' => ['' => '— None —'] + BankAccount::ledgerAccountOptions()],
            ['name' => 'account_name', 'label' => 'Account Name', 'type' => 'text', 'table' => true],
            ['name' => 'account_number', 'label' => 'Account Number', 'type' => 'text', 'table' => true],
            ['name' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text', 'table' => true],
            ['name' => 'branch', 'label' => 'Branch', 'type' => 'text'],
            ['name' => 'iban', 'label' => 'IBAN', 'type' => 'text'],
            ['name' => 'swift_code', 'label' => 'SWIFT Code', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'table' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'chart_of_account_id' => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false))],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255', CompanyRule::unique('accounting_bank_accounts', 'account_number')->ignore($record?->getKey())],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
