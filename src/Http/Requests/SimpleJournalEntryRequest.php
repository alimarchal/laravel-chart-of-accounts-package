<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Two-line entry in one call: debit one account, credit another, same amount.
 */
class SimpleJournalEntryRequest extends FormRequest
{
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
            'debit_account_code' => ['required', 'string', Rule::exists('accounting_chart_of_accounts', 'account_code')],
            'credit_account_code' => ['required', 'string', 'different:debit_account_code', Rule::exists('accounting_chart_of_accounts', 'account_code')],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'entry_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'post' => ['sometimes', 'boolean'],
        ];
    }
}
