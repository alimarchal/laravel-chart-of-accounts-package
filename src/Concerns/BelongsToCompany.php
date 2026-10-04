<?php

namespace Alimarchal\LaravelChartOfAccounts\Concerns;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyScope;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company-owned model: always read through the current company and created in it. company_id is not
 * mass-assignable and never changes once set (use CurrentCompany::runAs() to create in another company).
 *
 * @property int $company_id
 * @property-read Company $company
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('company_id')) {
                $model->setAttribute('company_id', app(CurrentCompany::class)->id());
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('company_id') && $model->getOriginal('company_id') !== null) {
                $model->setAttribute('company_id', $model->getOriginal('company_id'));
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
