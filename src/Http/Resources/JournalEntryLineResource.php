<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Resources;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntryLine
 */
class JournalEntryLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'chart_of_account_id' => $this->chart_of_account_id,
            'account_code' => $this->whenLoaded('account', fn () => $this->account->account_code),
            'account_name' => $this->whenLoaded('account', fn () => $this->account->account_name),
            'cost_center_id' => $this->cost_center_id,
            'cost_center_code' => $this->whenLoaded('costCenter', fn () => $this->costCenter?->code),
            'debit' => $this->debit,
            'credit' => $this->credit,
            'description' => $this->description,
            'reconciliation_status' => $this->reconciliation_status,
        ];
    }
}
