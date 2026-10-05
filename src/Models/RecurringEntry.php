<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A journal entry template that repeats on a schedule (see RecurringEntryService).
 *
 * @property int $id
 * @property string $name
 * @property int|null $voucher_type_id
 * @property string $frequency daily|weekly|monthly|quarterly|yearly
 * @property int $interval
 * @property int|null $day_of_month
 * @property CarbonInterface $start_date
 * @property CarbonInterface|null $end_date
 * @property CarbonInterface|null $next_run_date null once finished
 * @property int|null $max_runs
 * @property int $runs_count
 * @property string $mode draft|post
 * @property bool $is_active
 * @property string|null $reference
 * @property string|null $description
 * @property CarbonInterface|null $last_run_at
 */
class RecurringEntry extends AccountingModel
{
    use BelongsToCompany;

    public const FREQUENCIES = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    protected $table = 'accounting_recurring_entries';

    protected $fillable = ['name', 'voucher_type_id', 'frequency', 'interval', 'day_of_month', 'start_date', 'end_date', 'next_run_date', 'max_runs', 'runs_count', 'mode', 'is_active', 'reference', 'description', 'last_run_at'];

    protected function casts(): array
    {
        return [
            'interval' => 'integer',
            'day_of_month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_date' => 'date',
            'max_runs' => 'integer',
            'runs_count' => 'integer',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<RecurringEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RecurringEntryLine::class, 'recurring_entry_id')->orderBy('line_no');
    }

    /**
     * @return HasMany<RecurringEntryRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(RecurringEntryRun::class, 'recurring_entry_id')->orderByDesc('run_date');
    }

    /**
     * @return BelongsTo<VoucherType, $this>
     */
    public function voucherType(): BelongsTo
    {
        return $this->belongsTo(VoucherType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /**
     * active, paused or finished (the schedule ran out).
     */
    public function status(): string
    {
        return match (true) {
            $this->next_run_date === null => 'finished',
            ! $this->is_active => 'paused',
            default => 'active',
        };
    }
}
