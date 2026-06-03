<?php

namespace Alimarchal\LaravelChartOfAccounts\Concerns;

use Illuminate\Validation\Rule;

trait HasAccountingValidationRules
{
    /** @return array<int, string> */
    protected function moneyRules(): array
    {
        return ['numeric', 'min:0', 'decimal:0,2'];
    }

    /** @return array<int, string> */
    protected function positiveMoneyRules(): array
    {
        return ['required', 'numeric', 'gt:0', 'decimal:0,2'];
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
