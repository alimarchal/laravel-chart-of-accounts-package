<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Resources;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChartOfAccount
 */
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
            'control_type' => $this->control_type,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'parent_id' => $this->parent_id,
            'account_type_id' => $this->account_type_id,
            'currency_id' => $this->currency_id,
            'description' => $this->description,
            'account_type' => $this->whenLoaded('accountType'),
            'currency' => $this->whenLoaded('currency'),
            'children' => AccountResource::collection($this->whenLoaded('childrenRecursive')),
        ];
    }
}
