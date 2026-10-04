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
        static::saving(function (Currency $currency): void {
            if (! $currency->isDirty('is_base')) {
                return;
            }

            if (! $currency->is_base) {
                if ($currency->exists && $currency->getOriginal('is_base')) {
                    throw new AccountingException('There must always be a base currency: mark another currency as base instead.');
                }

                return;
            }

            $otherBase = static::query()->where('is_base', true)->whereKeyNot($currency->getKey())->exists();

            if ($otherBase && JournalEntry::query()->where('status', 'posted')->exists()) {
                throw new AccountingException('The base currency cannot be changed once journal entries have been posted.');
            }

            if ($otherBase) {
                // Exactly one base currency: switching the base demotes the previous one.
                static::query()->where('is_base', true)->whereKeyNot($currency->getKey())->update(['is_base' => false]);
            }
        });

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
