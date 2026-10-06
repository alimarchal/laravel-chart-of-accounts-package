<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property int $line_no
 * @property string|null $description
 * @property int $chart_of_account_id
 * @property int|null $cost_center_id
 * @property string $quantity
 * @property string $unit_price
 * @property int|null $tax_code_id
 * @property string|null $tax_rate
 * @property string $net_amount
 * @property string $tax_amount
 */
class PartyDocumentLine extends AccountingModel
{
    protected $table = 'accounting_party_document_lines';

    protected $fillable = ['document_id', 'line_no', 'description', 'chart_of_account_id', 'cost_center_id', 'quantity', 'unit_price', 'tax_code_id', 'tax_rate', 'net_amount', 'tax_amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'tax_rate' => 'decimal:4', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<PartyDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PartyDocument::class, 'document_id');
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }
}
