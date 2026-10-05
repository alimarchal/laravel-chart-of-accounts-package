<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One period-end revaluation run: the adjusting entry it posted and, optionally, the reversal that undoes it.
 *
 * @property int $id
 * @property CarbonInterface $as_of_date
 * @property int $gain_loss_account_id
 * @property int $journal_entry_id
 * @property int|null $reversal_entry_id
 * @property CarbonInterface|null $reversal_date
 * @property string $total_gain
 * @property string $total_loss
 * @property string|null $notes
 */
class FxRevaluation extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_fx_revaluations';

    protected $fillable = ['as_of_date', 'gain_loss_account_id', 'journal_entry_id', 'reversal_entry_id', 'reversal_date', 'total_gain', 'total_loss', 'notes'];

    protected function casts(): array
    {
        return ['as_of_date' => 'date', 'reversal_date' => 'date', 'total_gain' => 'decimal:2', 'total_loss' => 'decimal:2'];
    }

    /**
     * @return HasMany<FxRevaluationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(FxRevaluationLine::class, 'fx_revaluation_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_entry_id');
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function gainLossAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gain_loss_account_id');
    }
}
