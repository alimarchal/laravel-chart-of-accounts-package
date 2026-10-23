<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Database\Eloquent\Model;

class ReconciliationApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return Reconciliation::class;
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'bank_account_id' => ['required', CompanyRule::exists('accounting_bank_accounts', 'id')],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            // `status` and `book_balance` are never typed in: they come from the lines matched by the reconciliation process.
        ];
    }
}
