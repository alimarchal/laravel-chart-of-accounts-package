<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Alimarchal\LaravelChartOfAccounts\Concerns\HasBranch;
use Alimarchal\LaravelChartOfAccounts\Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $chart_of_account_id
 * @property bool $is_active
 */
class BankAccount extends AccountingModel
{
    use BelongsToCompany;
    use HasBranch;

    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    protected $table = 'accounting_bank_accounts';

    use SoftDeletes;

    protected static string $factory = BankAccountFactory::class;

    protected $fillable = [
        'chart_of_account_id',
        'account_name',
        'account_number',
        'bank_name',
        'branch',
        'iban',
        'swift_code',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The ledger accounts a bank account can be linked to (active asset posting accounts of the current company), id => "code - name".
     *
     * @return array<int, string>
     */
    public static function ledgerAccountOptions(): array
    {
        return ChartOfAccount::query()->where('is_group', false)->where('is_active', true)
            ->whereHas('accountType', fn ($query) => $query->where('code', 'ASSET'))
            ->orderBy('account_code')->get(['id', 'account_code', 'account_name'])
            ->mapWithKeys(fn (ChartOfAccount $account): array => [$account->id => $account->account_code.' - '.$account->account_name])->all();
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
