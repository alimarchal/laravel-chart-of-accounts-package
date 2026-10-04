<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of voucher (journal, cash payment, bank receipt, …) with its own number series.
 *
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property string $prefix
 * @property string $format
 * @property string $reset
 * @property bool $is_active
 * @property bool $is_system
 */
class VoucherType extends AccountingModel
{
    use BelongsToCompany;

    public const RESET_YEARLY = 'yearly';

    public const RESET_MONTHLY = 'monthly';

    public const RESET_NEVER = 'never';

    public const DEFAULT_FORMAT = '{PREFIX}-{FY}-{SEQ:5}';

    /** The voucher type used when an entry names none (also for reversals and closing entries of untyped entries). */
    public const DEFAULT_CODE = 'JV';

    protected $table = 'accounting_voucher_types';

    protected $fillable = [
        'code',
        'name',
        'prefix',
        'format',
        'reset',
        'description',
        'is_active',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * The voucher types every company starts with.
     *
     * @return array<int, array{code: string, name: string, prefix: string, description: string}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => 'JV', 'name' => 'Journal Voucher', 'prefix' => 'JV', 'description' => 'Adjustments, accruals, transfers and other non-cash entries.'],
            ['code' => 'CPV', 'name' => 'Cash Payment Voucher', 'prefix' => 'CPV', 'description' => 'Payments made in cash.'],
            ['code' => 'CRV', 'name' => 'Cash Receipt Voucher', 'prefix' => 'CRV', 'description' => 'Cash received.'],
            ['code' => 'BPV', 'name' => 'Bank Payment Voucher', 'prefix' => 'BPV', 'description' => 'Payments from a bank account (cheque, transfer).'],
            ['code' => 'BRV', 'name' => 'Bank Receipt Voucher', 'prefix' => 'BRV', 'description' => 'Receipts into a bank account.'],
        ];
    }

    /** @return array<int, string> */
    public static function resets(): array
    {
        return [self::RESET_YEARLY, self::RESET_MONTHLY, self::RESET_NEVER];
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'voucher_type_id');
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(VoucherSequence::class, 'voucher_type_id');
    }
}
