<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Illuminate\Database\Eloquent\Model;

class AccountBalanceSnapshotController extends SimpleAccountingResourceController
{
    protected function model(): string
    {
        return AccountBalanceSnapshot::class;
    }

    protected function routeName(): string
    {
        return 'account-balance-snapshots';
    }

    protected function title(): string
    {
        return 'Account Balance Snapshot';
    }

    protected function readOnly(): bool
    {
        return true;
    }

    protected function fields(): array
    {
        return [
            ['name' => 'chart_of_account_id', 'label' => 'Account ID', 'type' => 'number', 'table' => true, 'filter' => true],
            ['name' => 'accounting_period_id', 'label' => 'Period ID', 'type' => 'number', 'table' => true, 'filter' => true],
            ['name' => 'snapshot_date', 'label' => 'Snapshot Date', 'type' => 'date', 'table' => true],
            ['name' => 'opening_balance', 'label' => 'Opening Balance', 'type' => 'number', 'step' => '0.01', 'table' => true],
            ['name' => 'period_debits', 'label' => 'Period Debits', 'type' => 'number', 'step' => '0.01', 'table' => true],
            ['name' => 'period_credits', 'label' => 'Period Credits', 'type' => 'number', 'step' => '0.01', 'table' => true],
            ['name' => 'closing_balance', 'label' => 'Closing Balance', 'type' => 'number', 'step' => '0.01', 'table' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'chart_of_account_id' => ['required', 'exists:accounting_chart_of_accounts,id'],
            'accounting_period_id' => ['required', 'exists:accounting_periods,id'],
            'snapshot_date' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric'],
            'period_debits' => ['required', 'numeric'],
            'period_credits' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
        ];
    }
}
