<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A settled tax period: the output and input tax of the period offset against each other, the difference booked to
 * the payable account.
 *
 * @property int $id
 * @property CarbonInterface $period_from
 * @property CarbonInterface $period_to
 * @property string $output_tax
 * @property string $input_tax
 * @property string $net_payable negative = a refund is due
 * @property int $payable_account_id
 * @property int|null $journal_entry_id
 * @property string|null $reference
 * @property string|null $notes
 */
class TaxReturn extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_tax_returns';

    protected $fillable = ['period_from', 'period_to', 'output_tax', 'input_tax', 'net_payable', 'payable_account_id', 'journal_entry_id', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'output_tax' => 'decimal:2', 'input_tax' => 'decimal:2', 'net_payable' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
    }
}
