<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Services\BranchService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Alimarchal\LaravelChartOfAccounts\Support\ListFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BranchApiController extends Controller
{
    public function __construct(private readonly BranchService $service) {}

    /**
     * The branches of the company; "current" marks the working branch of this request (X-Branch header).
     */
    public function index(Request $request): JsonResponse
    {
        $current = CurrentBranch::selectedId();

        return response()->json([
            'data' => ListFilters::collection(
                Branch::query()->orderByDesc('is_head_office')->orderBy('name')->get()->map(fn (Branch $branch) => [...$this->present($branch), 'current' => $current === (int) $branch->getKey()])->values(),
                $request, ['code', 'name', 'city', 'address', 'phone'], ['is_active'],
            ),
            'branches_enabled' => CurrentBranch::enabled(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->enabled();
        $data = $request->validate($this->service->rules());

        return response()->json(['data' => $this->present($this->service->create($data))], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->enabled();

        return response()->json(['data' => [...$this->present($branch), 'members' => $this->service->members($branch)]]);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->enabled();
        $data = $request->validate(array_map(fn (array $rule) => ['sometimes', ...array_diff($rule, ['required'])], $this->service->rules($branch)));

        return response()->json(['data' => $this->present($this->service->update($branch, $data))]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->enabled();
        $this->service->delete($branch);

        return response()->json(null, 204);
    }

    public function grant(Request $request, Branch $branch): JsonResponse
    {
        $this->enabled();
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $users = config('auth.providers.users.model');

        $this->service->grant($branch, $users::query()->findOrFail($data['user_id']));

        return response()->json(['data' => $this->service->members($branch)]);
    }

    public function revoke(Branch $branch, int $user): JsonResponse
    {
        $this->enabled();
        $users = config('auth.providers.users.model');

        $this->service->revoke($branch, $users::query()->findOrFail($user));

        return response()->json(['data' => $this->service->members($branch)]);
    }

    private function enabled(): void
    {
        if (! CurrentBranch::enabled()) {
            throw new AccountingException('Branches are not enabled (ACCOUNTING_BRANCHES=true).');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Branch $branch): array
    {
        return $branch->only(['id', 'code', 'name', 'city', 'address', 'phone', 'is_head_office', 'is_active']);
    }
}
