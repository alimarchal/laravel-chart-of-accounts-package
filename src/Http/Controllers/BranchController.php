<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Services\BranchService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Alimarchal\LaravelChartOfAccounts\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branches screen (React): create and edit the branches of the company and choose who works in each.
 */
class BranchController extends Controller
{
    public function __construct(private readonly BranchService $service) {}

    public function index(Request $request): Response
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $users = config('auth.providers.users.model');

        return Inertia::render('accounting/branches/index', [
            'filters' => ListFilters::current($request, ['search', 'is_active']),
            'branches' => ListFilters::collection(Branch::query()->orderByDesc('is_head_office')->orderBy('name')->get()->map(fn (Branch $branch) => [
                ...$branch->only(['id', 'code', 'name', 'city', 'address', 'phone', 'is_head_office', 'is_active']),
                'members' => $this->service->members($branch),
            ])->values(), $request, ['code', 'name', 'city', 'address', 'phone'], ['is_active']),
            'users' => $users::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $request->merge(['is_head_office' => $request->boolean('is_head_office'), 'is_active' => $request->boolean('is_active', true)]);
        $branch = $this->service->create($request->validate($this->service->rules()));

        return back()->with('success', "Branch {$branch->name} created.");
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $request->merge(['is_head_office' => $request->boolean('is_head_office'), 'is_active' => $request->boolean('is_active', true)]);
        $this->service->update($branch, $request->validate($this->service->rules($branch)));

        return back()->with('success', "Branch {$branch->name} updated.");
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);

        try {
            $this->service->delete($branch);
        } catch (AccountingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Branch {$branch->name} deleted.");
    }

    public function grant(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $users = config('auth.providers.users.model');

        $this->service->grant($branch, $users::query()->findOrFail($data['user_id']));

        return back()->with('success', 'The person now works in this branch only (with the other branches given to them).');
    }

    public function revoke(Branch $branch, int $user): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $users = config('auth.providers.users.model');

        $this->service->revoke($branch, $users::query()->findOrFail($user));

        return back()->with('success', 'Access removed.');
    }
}
