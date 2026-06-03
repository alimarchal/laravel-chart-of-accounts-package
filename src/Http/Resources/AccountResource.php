<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_code' => $this->account_code,
            'account_name' => $this->account_name,
            'normal_balance' => $this->normal_balance,
            'is_group' => $this->is_group,
            'is_active' => $this->is_active,
            'parent_id' => $this->parent_id,
            'account_type' => $this->whenLoaded('accountType'),
            'currency' => $this->whenLoaded('currency'),
        ];
    }
}
