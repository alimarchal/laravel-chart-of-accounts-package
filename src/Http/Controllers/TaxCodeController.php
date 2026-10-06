<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
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
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'options' => self::kindLabels(), 'table' => true, 'filter' => true],
            ['name' => 'tax_account_id', 'label' => 'Tax account', 'type' => 'select', 'options' => ['' => 'None'] + ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name'])->mapWithKeys(fn (ChartOfAccount $account) => [(string) $account->id => $account->account_code.' - '.$account->account_name])->all()],
            ['name' => 'jurisdiction', 'label' => 'Jurisdiction', 'type' => 'text'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'table' => true, 'filter' => true],
        ];
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

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return ['output' => 'Output (collected on sales)', 'input' => 'Input (paid on purchases)', 'withheld' => 'Withheld from payments we make', 'advance' => 'Withheld from payments to us (advance tax)'];
    }
}
