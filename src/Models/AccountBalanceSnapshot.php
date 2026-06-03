<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends Model
{
    protected $table = 'accounting_account_balance_snapshots';

    protected $fillable = [
        'chart_of_account_id',
        'accounting_period_id',
        'snapshot_date',
        'opening_balance',
        'period_debits',
        'period_credits',
        'closing_balance',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'opening_balance' => 'decimal:2',
            'period_debits' => 'decimal:2',
            'period_credits' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }
}
