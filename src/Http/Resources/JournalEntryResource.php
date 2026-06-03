<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date?->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->status,
            'posted_at' => $this->posted_at?->toISOString(),
            'currency' => $this->whenLoaded('currency'),
            'accounting_period' => $this->whenLoaded('accountingPeriod'),
            'lines' => $this->whenLoaded('lines'),
        ];
    }
}
