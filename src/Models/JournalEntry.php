<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends AccountingModel
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;
    use SoftDeletes;

    protected static string $factory = JournalEntryFactory::class;

    protected $fillable = [
        'entry_date',
        'accounting_period_id',
        'currency_id',
        'fx_rate_to_base',
        'reference',
        'description',
        'status',
        'posted_at',
        'posted_by',
        'reverses_entry_id',
        'reversed_by_entry_id',
        'reversed_at',
        'is_closing_entry',
        'closes_period_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'fx_rate_to_base' => 'decimal:8',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
            'is_closing_entry' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id')->orderBy('line_no');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'), 'posted_by');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversedByEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }
}
