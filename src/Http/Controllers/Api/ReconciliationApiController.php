<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ReconciliationApiController extends SimpleAccountingApiController
{
    protected function model(): string
    {
        return Reconciliation::class;
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'bank_account_id' => ['required', 'exists:accounting_bank_accounts,id'],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'book_balance' => ['required', 'numeric'],
            'status' => ['required', Rule::in(['draft', 'completed', 'void'])],
        ];
    }
}
