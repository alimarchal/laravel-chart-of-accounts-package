<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Illuminate\Foundation\Auth\User;
use Alimarchal\LaravelChartOfAccounts\Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class JournalEntry extends AccountingModel
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    use SoftDeletes;


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
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversedByEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    /**
     * Create and optionally post a balanced journal entry in one call.
     *
     * Usage:
     *   JournalEntry::record(
     *       description: 'Salary payment',
     *       debitAccountCode: '6101',
     *       creditAccountCode: '1101',
     *       amount: 50000,
     *       post: true,           // false = save as draft
     *       reference: 'SAL-001', // optional
     *   );
     *
     * @param  string  $debitAccountCode  Account code for the debit line
     * @param  string  $creditAccountCode  Account code for the credit line
     * @param  bool  $post  true = post immediately, false = draft
     */
    public static function record(
        string $description,
        string $debitAccountCode,
        string $creditAccountCode,
        float $amount,
        bool $post = false,
        ?string $reference = null,
    ): static {
        $debitAccount = ChartOfAccount::where('account_code', $debitAccountCode)->firstOrFail();
        $creditAccount = ChartOfAccount::where('account_code', $creditAccountCode)->firstOrFail();
        $currency = Currency::where('is_base', true)->firstOrFail();
        $period = AccountingPeriod::where('status', 'open')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->firstOrFail();

        return DB::transaction(function () use ($description, $debitAccount, $creditAccount, $amount, $post, $reference, $currency, $period) {
            /** @var static $entry */
            $entry = static::create([
                'entry_date' => now()->toDateString(),
                'accounting_period_id' => $period->id,
                'currency_id' => $currency->id,
                'fx_rate_to_base' => 1,
                'reference' => $reference,
                'description' => $description,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $entry->lines()->createMany([
                ['line_no' => 1, 'chart_of_account_id' => $debitAccount->id, 'debit' => $amount, 'credit' => 0],
                ['line_no' => 2, 'chart_of_account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $amount],
            ]);

            if ($post) {
                app(JournalEntryService::class)->post($entry);
            }

            return $entry->fresh();
        });
    }
}
