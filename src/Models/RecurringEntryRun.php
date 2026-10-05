<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated occurrence: its scheduled date, the entry it made and how far it got.
 *
 * @property int $id
 * @property int $recurring_entry_id
 * @property CarbonInterface $run_date
 * @property string $status posted|submitted|draft|failed
 * @property int|null $journal_entry_id
 * @property string|null $error
 */
class RecurringEntryRun extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_recurring_entry_runs';

    protected $fillable = ['recurring_entry_id', 'run_date', 'status', 'journal_entry_id', 'error'];

    protected function casts(): array
    {
        return ['run_date' => 'date'];
    }

    /**
     * @return BelongsTo<RecurringEntry, $this>
     */
    public function recurringEntry(): BelongsTo
    {
        return $this->belongsTo(RecurringEntry::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
