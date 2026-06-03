<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'accounting_audit_logs';

    protected $fillable = [
        'table_name',
        'record_id',
        'action',
        'old_values',
        'new_values',
        'changed_fields',
        'metadata',
        'user_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class));
    }
}
