<?php

namespace Alimarchal\LaravelChartOfAccounts\Concerns;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Support\BranchScope;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record booked to a branch of the company. A new record gets the working branch (or the only branch of the user) unless a branch_id is given, null included (company-wide);
 * a user assigned to branches can read and write the records of those branches only (BranchScope). Without branches enabled nothing
 * of this applies.
 *
 * @property int|null $branch_id
 * @property-read Branch|null $branch
 */
trait HasBranch
{
    public static function bootHasBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            if (CurrentBranch::enabled() && ! array_key_exists('branch_id', $model->getAttributes())) {
                $branch = CurrentBranch::defaultId();

                // A user of several branches has to say which one this belongs to (it would be invisible to them with none).
                if ($branch === null && CurrentBranch::restricted()) {
                    throw new AccountingException('Choose your working branch first: this has to belong to one of your branches.');
                }

                $model->setAttribute('branch_id', $branch);
            }
        });
    }

    public function initializeHasBranch(): void
    {
        if (! in_array('branch_id', $this->fillable, true)) {
            $this->fillable[] = 'branch_id';
        }
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withoutGlobalScopes();
    }
}
