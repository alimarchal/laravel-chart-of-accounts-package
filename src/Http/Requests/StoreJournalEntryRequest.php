<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Requests;

use Alimarchal\LaravelChartOfAccounts\Concerns\HasAccountingValidationRules;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lines may reference accounts by id (chart_of_account_id) or by code (account_code), cost centers by
 * id or code, and the currency by id or ISO code — so API clients never have to look up internal ids.
 */
class StoreJournalEntryRequest extends FormRequest
{
    use HasAccountingValidationRules;

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $codes = collect($lines)->pluck('account_code')->filter()->unique()->values();
            $accounts = $codes->isEmpty() ? collect() : ChartOfAccount::query()->whereIn('account_code', $codes)->pluck('id', 'account_code');

            $centerCodes = collect($lines)->pluck('cost_center_code')->filter()->unique()->values();
            $centers = $centerCodes->isEmpty() ? collect() : CostCenter::query()->whereIn('code', $centerCodes)->pluck('id', 'code');

            $lines = array_map(function ($line) use ($accounts, $centers) {
                if (! is_array($line)) {
                    return $line;
                }

                if (empty($line['chart_of_account_id']) && ! empty($line['account_code']) && $accounts->has($line['account_code'])) {
                    $line['chart_of_account_id'] = $accounts->get($line['account_code']);
                }

                if (empty($line['cost_center_id']) && ! empty($line['cost_center_code']) && $centers->has($line['cost_center_code'])) {
                    $line['cost_center_id'] = $centers->get($line['cost_center_code']);
                }

                return $line;
            }, $lines);
        }

        $merge = ['auto_post' => $this->boolean('auto_post')];

        if (is_array($lines)) {
            $merge['lines'] = $lines;
        }

        if (! $this->filled('currency_id') && $this->filled('currency_code')) {
            $merge['currency_id'] = Currency::query()->where('code', strtoupper((string) $this->input('currency_code')))->value('id') ?? 0;
        }

        $this->merge($merge);
    }

    public function authorize(): bool
    {
        return ($this->user()?->can('journal-entries.create') ?? false) && $this->canAutoPost();
    }

    /**
     * Posting immediately (auto_post) requires the separate "post" permission,
     * so creating/editing drafts and posting them stay segregated duties.
     */
    protected function canAutoPost(): bool
    {
        return ! $this->boolean('auto_post') || ($this->user()?->can('journal-entries.post') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'currency_id' => ['nullable', Rule::exists('accounting_currencies', 'id')],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'fx_rate_to_base' => ['nullable', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'auto_post' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array', 'min:2', 'max:500'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.chart_of_account_id' => ['required_without:lines.*.account_code', 'nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false))],
            'lines.*.account_code' => ['nullable', 'string', CompanyRule::exists('accounting_chart_of_accounts', 'account_code')->where(fn ($query) => $query->where('is_group', false))],
            'lines.*.cost_center_id' => ['nullable', 'integer', CompanyRule::exists('accounting_cost_centers', 'id')],
            'lines.*.cost_center_code' => ['nullable', 'string', CompanyRule::exists('accounting_cost_centers', 'code')],
            'lines.*.debit' => $this->moneyRules(),
            'lines.*.credit' => $this->moneyRules(),
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.*.chart_of_account_id.required_without' => 'Each line needs a chart_of_account_id or an account_code.',
            'currency_id.exists' => 'The selected currency is invalid.',
            'lines.*.chart_of_account_id.exists' => 'Line :position: choose a posting account of this company (group accounts only total their children).',
            'lines.*.account_code.exists' => 'Line :position: choose a posting account of this company (group accounts only total their children).',
        ];
    }

    /**
     * Validated data in the shape JournalEntryService expects (codes resolved to ids).
     *
     * @return array<string, mixed>
     */
    public function journalData(): array
    {
        $data = $this->validated();
        unset($data['currency_code']);

        $data['lines'] = array_map(function (array $line): array {
            unset($line['account_code'], $line['cost_center_code']);

            return $line;
        }, $data['lines']);

        return $data;
    }
}
