<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Customers and suppliers.
 */
class PartyService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input, ?Party $party = null): array
    {
        $account = fn (string $type) => ['nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true)->whereIn('account_type_id', fn ($sub) => $sub->select('id')->from('accounting_account_types')->where('code', $type)))];

        return Validator::make($input, [
            'type' => ['required', Rule::in(Party::TYPES)],
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_parties', 'code')->ignore($party?->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tax_number' => ['nullable', 'string', 'max:40'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'receivable_account_id' => $account('ASSET'),
            'payable_account_id' => $account('LIABILITY'),
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'receivable_account_id.exists' => 'Choose an active asset posting account of this company.',
            'payable_account_id.exists' => 'Choose an active liability posting account of this company.',
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): Party
    {
        $party = new Party($this->attributes($data));
        $party->save();
        AccountingAuditLog::record($party, 'PARTY_CREATED', null, ['code' => $party->code, 'name' => $party->name, 'type' => $party->type]);

        return $party;
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function update(Party $party, array $data): Party
    {
        $type = $data['type'];

        if (($type === 'customer' && $party->documents()->whereIn('kind', ['bill', 'debit_note'])->exists())
            || ($type === 'supplier' && $party->documents()->whereIn('kind', ['invoice', 'credit_note'])->exists())) {
            throw new AccountingException('The party has documents of the kind its new type would not allow.');
        }

        $before = ['code' => $party->code, 'name' => $party->name, 'type' => $party->type, 'is_active' => $party->is_active];
        $party->fill($this->attributes($data))->save();
        AccountingAuditLog::record($party, 'PARTY_UPDATED', $before, ['code' => $party->code, 'name' => $party->name, 'type' => $party->type, 'is_active' => $party->is_active]);

        return $party->refresh();
    }

    public function delete(Party $party): void
    {
        if ($party->documents()->exists() || $party->payments()->exists()) {
            throw new AccountingException('A party with documents or payments cannot be deleted: deactivate it instead.');
        }

        AccountingAuditLog::record($party, 'PARTY_DELETED', ['code' => $party->code, 'name' => $party->name]);
        $party->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'code' => $data['code'],
            'name' => $data['name'],
            'email' => ($data['email'] ?? null) ?: null,
            'phone' => ($data['phone'] ?? null) ?: null,
            'address' => ($data['address'] ?? null) ?: null,
            'tax_number' => ($data['tax_number'] ?? null) ?: null,
            'payment_terms_days' => (int) ($data['payment_terms_days'] ?? 30),
            'credit_limit' => ($data['credit_limit'] ?? null) === null || $data['credit_limit'] === '' ? null : $data['credit_limit'],
            'receivable_account_id' => ($data['receivable_account_id'] ?? null) ?: null,
            'payable_account_id' => ($data['payable_account_id'] ?? null) ?: null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => ($data['notes'] ?? null) ?: null,
        ];
    }
}
