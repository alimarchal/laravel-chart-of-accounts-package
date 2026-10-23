<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Alimarchal\LaravelChartOfAccounts\Database\Factories\ReconciliationFactory;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reconciliation extends AccountingModel
{
    use BelongsToCompany;

    /** @use HasFactory<ReconciliationFactory> */
    use HasFactory;

    protected $table = 'accounting_reconciliations';

    protected static string $factory = ReconciliationFactory::class;

    protected $fillable = [
        'bank_account_id',
        'statement_date',
        'statement_balance',
        'book_balance',
        'status',
        'completed_at',
        'completed_by',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        // A completed reconciliation is evidence: its statement cannot be edited afterwards.
        static::updating(function (Reconciliation $reconciliation): void {
            if ($reconciliation->getOriginal('status') === 'completed' && $reconciliation->isDirty(['bank_account_id', 'statement_date', 'statement_balance'])) {
                throw new AccountingException('A completed reconciliation cannot be edited.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'decimal:2',
            'book_balance' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'reconciliation_id');
    }
}
