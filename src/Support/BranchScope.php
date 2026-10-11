<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A user assigned to branches sees (and so can change) only the records of those branches. Records with no branch (head office / not
 * allocated) are for users without an assignment. The working-branch selection is not applied here: lists and reports use
 * CurrentBranch::constrain() for that, so that period close, posting and the like always see the whole company.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ids = CurrentBranch::accessibleIds();

        if ($ids !== null) {
            $builder->whereIn($model->qualifyColumn('branch_id'), $ids);
        }
    }
}
