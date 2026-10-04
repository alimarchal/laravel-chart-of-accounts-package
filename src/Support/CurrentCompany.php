<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The company the current request, job or command works on. Every company-owned model is scoped to it.
 *
 * Resolution (first match): an explicit set()/runAs(), then — with multi-company enabled — the
 * X-Company header (API), the company chosen in the session (web), the user's default company and
 * finally the first company the user may access; otherwise the default company.
 * It is resolved lazily, so route-model binding is scoped correctly regardless of middleware order;
 * EnsureAccountingCompanyAccess then rejects companies the user may not access.
 */
class CurrentCompany
{
    public const SESSION_KEY = 'accounting.company_id';

    /** Set explicitly (runAs, commands, tests); wins over request resolution. */
    private ?Company $explicit = null;

    private ?Company $resolved = null;

    /** The request $resolved belongs to: a new request (Octane, tests) resolves again. */
    private ?object $resolvedFor = null;

    public function get(): Company
    {
        if ($this->explicit !== null) {
            return $this->explicit;
        }

        $request = app()->bound('request') ? request() : null;

        if ($this->resolved === null || $this->resolvedFor !== $request) {
            $this->resolved = $this->resolve();
            $this->resolvedFor = $request;
        }

        return $this->resolved;
    }

    public function id(): int
    {
        return (int) $this->get()->getKey();
    }

    /** @var array<int, int>|null Companies included in reports (consolidation); null = the current one. */
    private ?array $reportCompanies = null;

    /**
     * Company ids reports aggregate over: the current company, or several inside consolidate().
     *
     * @return array<int, int>
     */
    public function reportIds(): array
    {
        return $this->reportCompanies ?? [$this->id()];
    }

    /**
     * Run reports over several companies (consolidation). Access is checked by the caller.
     *
     * @template T
     *
     * @param  array<int, int>  $companyIds
     * @param  callable(): T  $callback
     * @return T
     */
    public function consolidate(array $companyIds, callable $callback): mixed
    {
        $previous = $this->reportCompanies;
        $this->reportCompanies = array_values(array_unique(array_map('intval', $companyIds)));

        try {
            return $callback();
        } finally {
            $this->reportCompanies = $previous;
        }
    }

    /** Shortcut for raw queries: the current company id. */
    public static function currentId(): int
    {
        return app(self::class)->id();
    }

    /**
     * Shortcut for report queries: the company ids being reported on.
     *
     * @return array<int, int>
     */
    public static function ids(): array
    {
        return app(self::class)->reportIds();
    }

    public function set(Company $company): void
    {
        $this->explicit = $company;
    }

    /** The explicitly set company, to restore later (commands run inside another context). */
    public function explicit(): ?Company
    {
        return $this->explicit;
    }

    public function restore(?Company $company): void
    {
        $this->explicit = $company;
    }

    public function forget(): void
    {
        $this->explicit = null;
        $this->resolved = null;
        $this->resolvedFor = null;
    }

    /**
     * Run a callback as another company (jobs, commands, seeding a new company).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Company|int $company, callable $callback): mixed
    {
        $previous = $this->explicit;
        $this->explicit = $company instanceof Company ? $company : Company::query()->findOrFail($company);

        try {
            return $callback();
        } finally {
            $this->explicit = $previous;
        }
    }

    public static function enabled(): bool
    {
        return (bool) config('accounting.multi_company.enabled', false);
    }

    public function default(): Company
    {
        $code = (string) config('accounting.multi_company.default_company_code', 'MAIN');

        return Company::query()->where('code', $code)->first()
            ?? Company::query()->orderBy('id')->first()
            ?? throw new AccountingException('No company exists. Run "php artisan migrate" (it creates the default company).');
    }

    /**
     * Companies the user may work in. Without multi-company: only the default company.
     *
     * @return Collection<int, Company>
     */
    public function accessibleBy(?Authenticatable $user): Collection
    {
        if (! self::enabled()) {
            return collect([$this->default()]);
        }

        if ($user === null) {
            return collect();
        }

        if ($this->isUnrestricted($user)) {
            return Company::query()->where('is_active', true)->orderBy('name')->get();
        }

        return Company::query()
            ->where('is_active', true)
            ->whereIn('id', DB::table('accounting_company_user')->where('user_id', $user->getAuthIdentifier())->select('company_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Companies chosen for a group report: "MAIN,SUB" (codes or ids), or every accessible company.
     * Unknown companies are 404, inaccessible ones 403.
     *
     * @return Collection<int, Company>
     */
    public function select(?Authenticatable $user, ?string $selection): Collection
    {
        if ($selection === null || trim($selection) === '') {
            return $this->accessibleBy($user)->values();
        }

        return collect(explode(',', $selection))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->unique()
            ->map(function (string $value) use ($user): Company {
                $company = $this->find($value);
                abort_if($company === null, 404, "Unknown company \"{$value}\".");
                abort_unless($this->canAccess($user, $company), 403, "You do not have access to company \"{$value}\".");

                return $company;
            })
            ->values();
    }

    public function canAccess(?Authenticatable $user, Company $company): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if ($user === null || ! $company->is_active) {
            return false;
        }

        return $this->isUnrestricted($user)
            || DB::table('accounting_company_user')->where('user_id', $user->getAuthIdentifier())->where('company_id', $company->getKey())->exists();
    }

    /**
     * Super-admins work in every company.
     */
    public function isUnrestricted(Authenticatable $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super-admin');
    }

    /**
     * The company asked for by the request (header or session), or null. Does not check access.
     */
    public function requested(Request $request): ?Company
    {
        $header = $request->header((string) config('accounting.multi_company.header', 'X-Company'));

        if (is_string($header) && $header !== '') {
            return $this->find($header) ?? throw new AccountingException("Unknown company \"{$header}\".");
        }

        if ($request->hasSession() && $request->session()->has(self::SESSION_KEY)) {
            return Company::query()->find($request->session()->get(self::SESSION_KEY));
        }

        return null;
    }

    public function find(string|int $idOrCode): ?Company
    {
        return Company::query()
            ->where('code', (string) $idOrCode)
            ->when(ctype_digit((string) $idOrCode), fn ($query) => $query->orWhere('id', (int) $idOrCode))
            ->first();
    }

    private function resolve(): Company
    {
        // Artisan commands and anything outside an HTTP request use the default company unless set().
        if (! self::enabled() || ! app()->bound('request') || request()->route() === null) {
            return $this->default();
        }

        $request = request();

        if ($requested = $this->requested($request)) {
            return $requested;
        }

        $user = $request->user();

        if ($user !== null) {
            $default = DB::table('accounting_company_user')
                ->where('user_id', $user->getAuthIdentifier())
                ->where('is_default', true)
                ->value('company_id');

            if ($default && ($company = Company::query()->find($default))) {
                return $company;
            }

            if ($first = $this->accessibleBy($user)->first()) {
                return $first;
            }
        }

        return $this->default();
    }
}
