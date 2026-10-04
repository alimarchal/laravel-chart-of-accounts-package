<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Resources;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status,
            'currency_id' => $this->currency_id,
            'fx_rate_to_base' => $this->fx_rate_to_base,
            'accounting_period_id' => $this->accounting_period_id,
            'posted_at' => $this->posted_at?->toISOString(),
            'is_reversed' => $this->reversed_by_entry_id !== null,
            'reversed_by_entry_id' => $this->reversed_by_entry_id,
            'reverses_entry_id' => $this->reverses_entry_id,
            'total_debit' => $this->whenLoaded('lines', fn () => Money::fromCents($this->lines->sum(fn ($line) => Money::toCents($line->getRawOriginal('debit'))))),
            'total_credit' => $this->whenLoaded('lines', fn () => Money::fromCents($this->lines->sum(fn ($line) => Money::toCents($line->getRawOriginal('credit'))))),
            'currency' => $this->whenLoaded('currency'),
            'accounting_period' => $this->whenLoaded('accountingPeriod'),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
