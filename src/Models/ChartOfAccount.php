<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Alimarchal\LaravelChartOfAccounts\Database\Factories\ChartOfAccountFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int $account_type_id
 * @property int $currency_id
 * @property string $account_code
 * @property string $account_name
 * @property string $normal_balance debit|credit
 * @property string|null $description
 * @property bool $is_group
 * @property bool $is_active
 * @property bool $is_system
 * @property-read ChartOfAccount|null $parent
 * @property-read Collection<int, ChartOfAccount> $children
 * @property-read Collection<int, ChartOfAccount> $childrenRecursive
 * @property-read AccountType $accountType
 */
class ChartOfAccount extends AccountingModel
{
    use BelongsToCompany;

    /** @use HasFactory<ChartOfAccountFactory> */
    use HasFactory;

    protected $table = 'accounting_chart_of_accounts';

    protected static string $factory = ChartOfAccountFactory::class;

    protected $fillable = [
        'parent_id',
        'account_type_id',
        'currency_id',
        'account_code',
        'account_name',
        'normal_balance',
        'description',
        'is_group',
        'is_active',
        'is_system',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('account_code');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with(['childrenRecursive', 'accountType']);
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'chart_of_account_id');
    }
}
