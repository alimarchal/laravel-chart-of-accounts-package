<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Companies screen (React): create and edit companies, and choose who may work in each.
 */
class CompanyController extends Controller
{
    public function __construct(private readonly CurrentCompany $companies, private readonly CompanyService $service) {}

    public function index(Request $request): Response
    {
        $users = config('auth.providers.users.model');

        return Inertia::render('accounting/companies/index', [
            'companies' => $this->companies->accessibleBy($request->user())->map(fn (Company $company) => [
                ...$company->only(['id', 'code', 'name', 'legal_name', 'tax_number', 'registration_number', 'email', 'phone', 'address', 'fiscal_year_start_month', 'is_active']),
                'members' => $this->service->members($company),
            ])->values(),
            'users' => $users::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge($this->service->normalize($request->only('code')));
        $data = $request->validate([...$this->service->rules(), 'seed' => ['sometimes', 'boolean']]);
        $seed = (bool) ($data['seed'] ?? true);
        unset($data['seed']);

        $company = $this->service->create($data, $seed, $request->user());

        return back()->with('success', "Company {$company->name} created.");
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompany($request, $company);
        $request->merge($this->service->normalize($request->only('code')));

        try {
            $this->service->update($company, $request->validate($this->service->rules($company)));
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Company {$company->name} updated.");
    }

    public function grant(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeCompany($request, $company);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'is_default' => ['sometimes', 'boolean']]);
        $users = config('auth.providers.users.model');

        $this->service->grantAccess($company, $users::query()->findOrFail($data['user_id']), (bool) ($data['is_default'] ?? false), $request->user());

        return back()->with('success', 'Access granted.');
    }

    public function revoke(Request $request, Company $company, int $user): RedirectResponse
    {
        $this->authorizeCompany($request, $company);
        $users = config('auth.providers.users.model');

        $this->service->revokeAccess($company, $users::query()->findOrFail($user), $request->user());

        return back()->with('success', 'Access removed.');
    }

    private function authorizeCompany(Request $request, Company $company): void
    {
        abort_unless($this->companies->canAccess($request->user(), $company), 403, 'You do not have access to this company.');
    }
}
