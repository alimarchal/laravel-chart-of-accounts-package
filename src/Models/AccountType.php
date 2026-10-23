<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Database\Factories\AccountTypeFactory;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountType extends AccountingModel
{
    /** @use HasFactory<AccountTypeFactory> */
    use HasFactory;

    protected $table = 'accounting_account_types';

    protected static string $factory = AccountTypeFactory::class;

    protected $fillable = [
        'code',
        'name',
        'normal_balance',
        'report_group',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        // Types are global and other modules look them up by code: once accounts (of any company) use a type, what it means is fixed.
        static::updating(function (AccountType $type): void {
            if ($type->isDirty(['code', 'normal_balance', 'report_group']) && ChartOfAccount::query()->withoutGlobalScopes()->where('account_type_id', $type->id)->exists()) {
                throw new AccountingException("Account type {$type->getOriginal('code')} is used by accounts: its code, normal balance and report group can no longer change.");
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'account_type_id');
    }
}
