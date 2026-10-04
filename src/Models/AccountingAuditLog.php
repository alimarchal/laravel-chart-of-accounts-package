<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AccountingAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'accounting_audit_logs';

    protected $fillable = [
        'company_id',
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

    /**
     * The audit trail of the current company, plus changes to shared tables (currencies, account types).
     */
    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $query->where(fn (Builder $query) => $query
                ->where($query->qualifyColumn('company_id'), CurrentCompany::currentId())
                ->orWhereNull($query->qualifyColumn('company_id')));
        });
    }

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
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Write an application-level audit record for a business action on a model.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(Model $model, string $action, ?array $oldValues = null, ?array $newValues = null, ?array $metadata = null): self
    {
        $request = app()->runningInConsole() ? null : request();

        return static::query()->create([
            'company_id' => match (true) {
                $model instanceof Company => $model->getKey(),
                $model->getAttribute('company_id') !== null => $model->getAttribute('company_id'),
                $model instanceof Currency, $model instanceof AccountType => null,
                default => CurrentCompany::currentId(),
            },
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $newValues !== null ? array_keys($newValues) : null,
            'metadata' => $metadata,
            'user_id' => Auth::id(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
