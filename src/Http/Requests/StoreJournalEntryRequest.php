<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Requests;

use App\Concerns\Accounting\HasAccountingValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    use HasAccountingValidationRules;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_post' => $this->boolean('auto_post'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('journal-entries.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'currency_id' => ['nullable', 'exists:accounting_currencies,id'],
            'fx_rate_to_base' => ['nullable', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'auto_post' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'exists:accounting_chart_of_accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'exists:accounting_cost_centers,id'],
            'lines.*.debit' => $this->moneyRules(),
            'lines.*.credit' => $this->moneyRules(),
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
