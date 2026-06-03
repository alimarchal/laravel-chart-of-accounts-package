<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use App\Models\User;
use Alimarchal\LaravelChartOfAccounts\Database\Factories\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPeriod extends AccountingModel
{
    /** @use HasFactory<AccountingPeriodFactory> */
    use HasFactory;

    protected static string $factory = AccountingPeriodFactory::class;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'closing_journal_entry_id',
        'closing_total_debits',
        'closing_total_credits',
        'closing_net_income',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'closing_total_debits' => 'decimal:2',
            'closing_total_credits' => 'decimal:2',
            'closing_net_income' => 'decimal:2',
        ];
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'accounting_period_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function closingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'closing_journal_entry_id');
    }
}
