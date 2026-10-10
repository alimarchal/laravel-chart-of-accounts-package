<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A feature switch set by an administrator (see FeatureManager). Installation-wide: it belongs to no company.
 *
 * @property int $id
 * @property string $feature
 * @property bool $enabled
 * @property int|null $updated_by
 */
class Feature extends Model
{
    protected $table = 'accounting_features';

    protected $fillable = ['feature', 'enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
