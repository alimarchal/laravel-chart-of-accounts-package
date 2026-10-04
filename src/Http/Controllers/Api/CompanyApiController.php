<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Reports\ConsolidatedReport;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CompanyApiController extends Controller
{
    public function __construct(private readonly CurrentCompany $companies, private readonly CompanyService $service) {}

    /**
     * Companies the caller can work in; "current" marks the one this request runs in.
     */
    public function index(Request $request): JsonResponse
    {
        $current = $this->companies->id();

        return response()->json([
            'data' => $this->companies->accessibleBy($request->user())
                ->map(fn (Company $company) => [...$this->present($company), 'current' => (int) $company->getKey() === $current])
                ->values(),
            'multi_company' => CurrentCompany::enabled(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge($this->service->normalize($request->only('code')));
        $data = $request->validate([...$this->service->rules(), 'seed' => ['sometimes', 'boolean']]);
        $seed = (bool) ($data['seed'] ?? true);
        unset($data['seed']);

        $company = $this->service->create($data, $seed, $request->user());

        return response()->json(['data' => $this->present($company)], 201);
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompany($request, $company);

        return response()->json(['data' => [...$this->present($company), 'members' => $this->service->members($company)]]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompany($request, $company);
        $request->merge($this->service->normalize($request->only('code')));
        $rules = $this->service->rules($company);
        $data = $request->validate(array_map(fn (array $rule) => ['sometimes', ...array_diff($rule, ['required'])], $rules));

        return response()->json(['data' => $this->present($this->service->update($company, $data))]);
    }

    public function grant(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompany($request, $company);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $this->service->grantAccess($company, $this->user($data['user_id']), (bool) ($data['is_default'] ?? false), $request->user());

        return response()->json(['data' => $this->service->members($company)]);
    }

    public function revoke(Request $request, Company $company, int $user): JsonResponse
    {
        $this->authorizeCompany($request, $company);
        $this->service->revokeAccess($company, $this->user($user), $request->user());

        return response()->json(['data' => $this->service->members($company)]);
    }

    /**
     * Group report over several companies: ?companies=MAIN,SUB (codes or ids; default: all the caller can access).
     */
    public function consolidated(Request $request, string $report, ConsolidatedReport $consolidated): JsonResponse
    {
        $filters = $request->validate([
            'companies' => ['nullable', 'string'],
            'as_of_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'include_zero' => ['nullable', 'boolean'],
        ]);

        $filters['include_zero'] = $request->boolean('include_zero');

        return response()->json($consolidated->build($report, $this->companies->select($request->user(), $filters['companies'] ?? null), $filters));
    }

    private function authorizeCompany(Request $request, Company $company): void
    {
        abort_unless($this->companies->canAccess($request->user(), $company), 403, 'You do not have access to this company.');
    }

    private function user(int $id): Authenticatable
    {
        $model = config('auth.providers.users.model');

        return $model::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Company $company): array
    {
        return $company->only(['id', 'code', 'name', 'legal_name', 'tax_number', 'registration_number', 'email', 'phone', 'address', 'fiscal_year_start_month', 'is_active']);
    }
}
