<?php

namespace Alimarchal\LaravelChartOfAccounts\Concerns;

use Illuminate\Validation\Rule;

trait HasAccountingValidationRules
{
    protected function maxAmount(): string
    {
        return number_format((float) config('accounting.max_amount', 999999999999.99), 2, '.', '');
    }

    /** @return array<int, string> */
    protected function moneyRules(): array
    {
        return ['numeric', 'min:0', 'max:'.$this->maxAmount(), 'decimal:0,2'];
    }

    /** @return array<int, string> */
    protected function positiveMoneyRules(): array
    {
        return ['required', 'numeric', 'gt:0', 'max:'.$this->maxAmount(), 'decimal:0,2'];
    }

    /** @return array<int, mixed> */
    protected function normalBalanceRules(): array
    {
        return ['required', Rule::in(['debit', 'credit'])];
    }

    /** @return array<int, mixed> */
    protected function reportGroupRules(): array
    {
        return ['required', Rule::in(['BalanceSheet', 'IncomeStatement'])];
    }
}
