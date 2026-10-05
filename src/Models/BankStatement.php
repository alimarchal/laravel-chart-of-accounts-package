<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One imported bank statement file.
 *
 * @property int $id
 * @property int $bank_account_id
 * @property string $file_name
 * @property CarbonInterface|null $from_date
 * @property CarbonInterface|null $to_date
 * @property string|null $closing_balance
 * @property int $lines_count
 * @property int|null $reconciliation_id
 */
class BankStatement extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_bank_statements';

    protected $fillable = ['bank_account_id', 'file_name', 'from_date', 'to_date', 'closing_balance', 'lines_count', 'reconciliation_id'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'closing_balance' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->orderBy('line_no');
    }

    /**
     * @return BelongsTo<Reconciliation, $this>
     */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }
}
