<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingChartOfAccountSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingCostCenterSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingPeriodSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingTaxCodeSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingTaxRateSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyService
{
    public function __construct(private readonly CurrentCompany $companies) {}

    /**
     * Company codes are stored upper-case; normalise input before validating so the unique rule matches.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        if (isset($input['code']) && is_string($input['code'])) {
            $input['code'] = strtoupper(trim($input['code']));
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(?Company $company = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('accounting_companies', 'code')->ignore($company?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'between:1,12'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Create a company with a ready-to-use chart of accounts, the current fiscal year, cost centers
     * and tax codes (seed: false creates an empty company). The creator gets access to it.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, bool $seed = true, ?Authenticatable $creator = null): Company
    {
        return DB::transaction(function () use ($data, $seed, $creator): Company {
            $company = Company::query()->create([
                ...$data,
                'code' => strtoupper((string) $data['code']),
                'fiscal_year_start_month' => $data['fiscal_year_start_month'] ?? 1,
            ]);

            if ($seed) {
                $this->companies->runAs($company, function (): void {
                    foreach ([AccountingPeriodSeeder::class, AccountingChartOfAccountSeeder::class, AccountingCostCenterSeeder::class, AccountingTaxCodeSeeder::class, AccountingTaxRateSeeder::class] as $seeder) {
                        app($seeder)->run();
                    }
                });
            }

            if ($creator !== null && ! $this->companies->isUnrestricted($creator)) {
                $this->grantAccess($company, $creator);
            }

            $this->companies->runAs($company, fn () => AccountingAuditLog::record($company, 'COMPANY_CREATED', null, $company->only(['code', 'name'])));

            return $company;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        $before = $company->only(array_keys($data));

        if (array_key_exists('code', $data)) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        if (($data['is_active'] ?? true) === false && $company->is($this->companies->default())) {
            throw new AccountingException('The default company cannot be deactivated.');
        }

        $company->update($data);
        $this->companies->runAs($company, fn () => AccountingAuditLog::record($company, 'COMPANY_UPDATED', $before, $company->only(array_keys($data))));

        return $company;
    }

    /**
     * Give a user access to a company. The actor must be able to access it themselves.
     */
    public function grantAccess(Company $company, Authenticatable $user, bool $default = false, ?Authenticatable $actor = null): void
    {
        $this->assertActorCanManage($company, $actor);

        DB::transaction(function () use ($company, $user, $default): void {
            if ($default) {
                DB::table('accounting_company_user')->where('user_id', $user->getAuthIdentifier())->update(['is_default' => false]);
            }

            DB::table('accounting_company_user')->updateOrInsert(
                ['company_id' => $company->getKey(), 'user_id' => $user->getAuthIdentifier()],
                ['is_default' => $default, 'updated_at' => now(), 'created_at' => now()],
            );
        });

        $this->recordAccessChange($company, $user, 'COMPANY_ACCESS_GRANTED', ['is_default' => $default]);
    }

    public function revokeAccess(Company $company, Authenticatable $user, ?Authenticatable $actor = null): void
    {
        $this->assertActorCanManage($company, $actor);

        DB::table('accounting_company_user')
            ->where('company_id', $company->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();

        $this->recordAccessChange($company, $user, 'COMPANY_ACCESS_REVOKED');
    }

    /**
     * Users with access to the company (super-admins have access without being listed).
     *
     * @return Collection<int, \stdClass>
     */
    public function members(Company $company): Collection
    {
        $users = config('accounting.users_table', 'users');

        return DB::table('accounting_company_user as cu')
            ->join("{$users} as u", 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $company->getKey())
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'cu.is_default']);
    }

    private function assertActorCanManage(Company $company, ?Authenticatable $actor): void
    {
        if ($actor !== null && ! $this->companies->canAccess($actor, $company) && ! $this->companies->isUnrestricted($actor)) {
            throw new AccountingException('You can only manage access to companies you can access yourself.');
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function recordAccessChange(Company $company, Authenticatable $user, string $action, array $extra = []): void
    {
        $this->companies->runAs($company, fn () => AccountingAuditLog::record(
            $company,
            $action,
            null,
            ['user_id' => $user->getAuthIdentifier(), 'email' => $user instanceof Model ? $user->getAttribute('email') : null, ...$extra],
        ));
    }
}
