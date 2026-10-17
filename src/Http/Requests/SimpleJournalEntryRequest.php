<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Requests;

use Alimarchal\LaravelChartOfAccounts\Concerns\HasAccountingValidationRules;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\SourceDocuments;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Two-line entry in one call: debit one account, credit another, same amount.
 */
class SimpleJournalEntryRequest extends FormRequest
{
    use HasAccountingValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('journal-entries.create') ?? false)
            && (! $this->boolean('post', true) || $user->can('journal-entries.post'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'debit_account_code' => ['required', 'string', CompanyRule::exists('accounting_chart_of_accounts', 'account_code')->where(fn ($query) => $query->where('is_group', false))],
            'credit_account_code' => ['required', 'string', 'different:debit_account_code', CompanyRule::exists('accounting_chart_of_accounts', 'account_code')->where(fn ($query) => $query->where('is_group', false))],
            'amount' => $this->positiveMoneyRules(),
            'entry_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:255'],
            ...SourceDocuments::rules(),
            'post' => ['sometimes', 'boolean'],
        ];
    }
}
