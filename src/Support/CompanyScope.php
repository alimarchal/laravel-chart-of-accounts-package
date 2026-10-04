<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits company-owned models to the current company. Remove it explicitly with
 * Model::withoutGlobalScope(CompanyScope::class) for cross-company work (consolidation, admin).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('company_id'), app(CurrentCompany::class)->id());
    }
}
