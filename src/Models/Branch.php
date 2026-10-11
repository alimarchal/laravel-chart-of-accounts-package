<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Alimarchal\LaravelChartOfAccounts\Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A place of business of a company (head office, campus, shop). Entries, invoices, bills and payments can be booked to a branch, and
 * reports limited to one. A user assigned to branches works in those only.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $city
 * @property string|null $address
 * @property string|null $phone
 * @property bool $is_head_office
 * @property bool $is_active
 */
class Branch extends AccountingModel
{
    use BelongsToCompany;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected $table = 'accounting_branches';

    protected static string $factory = BranchFactory::class;

    protected $fillable = ['code', 'name', 'city', 'address', 'phone', 'is_head_office', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_head_office' => 'boolean', 'is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('auth.providers.users.model'), 'accounting_branch_user', 'branch_id', 'user_id')->withTimestamps();
    }
}
