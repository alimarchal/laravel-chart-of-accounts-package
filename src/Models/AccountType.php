<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Database\Factories\AccountTypeFactory;
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
