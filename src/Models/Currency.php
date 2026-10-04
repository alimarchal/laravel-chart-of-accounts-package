<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Database\Factories\CurrencyFactory;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends AccountingModel
{
    protected static function booted(): void
    {
        static::deleting(function (Currency $currency): void {
            if ($currency->is_base) {
                throw new AccountingException('The base currency cannot be deleted.');
            }
        });
    }

    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $table = 'accounting_currencies';

    protected static string $factory = CurrencyFactory::class;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate_to_base',
        'is_base',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate_to_base' => 'decimal:8',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'currency_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'currency_id');
    }
}
