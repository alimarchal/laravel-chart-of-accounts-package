<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * A company-level setting kept in the database (the chart the company started from, ...).
 *
 * @property string $key
 * @property string|null $value
 */
class AccountingSetting extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_settings';

    protected $fillable = ['key', 'value'];

    public static function read(string $key, ?string $default = null): ?string
    {
        try {
            return Schema::hasTable('accounting_settings') ? (static::query()->where('key', $key)->value('value') ?? $default) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function write(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
