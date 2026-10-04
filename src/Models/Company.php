<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A legal entity with its own chart of accounts, periods, journal and reports. Account types and
 * currencies are shared by all companies; every company reports in the shared base currency.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $tax_number
 * @property string|null $registration_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $logo_path
 * @property int $fiscal_year_start_month
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 */
class Company extends AccountingModel
{
    protected $table = 'accounting_companies';

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'tax_number',
        'registration_number',
        'email',
        'phone',
        'address',
        'logo_path',
        'fiscal_year_start_month',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('auth.providers.users.model'), 'accounting_company_user', 'company_id', 'user_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
