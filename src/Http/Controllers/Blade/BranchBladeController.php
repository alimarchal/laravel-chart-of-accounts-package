<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Services\BranchService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BranchBladeController extends Controller
{
    public function __construct(private readonly BranchService $service) {}

    public function index(): View
    {
        abort_unless(CurrentBranch::enabled(), 404);

        $branches = QueryBuilder::for(Branch::query()->when(CurrentBranch::accessibleIds() !== null, fn ($query) => $query->whereIn('id', CurrentBranch::accessibleIds())), request())
            ->allowedFilters(
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('city'),
                AllowedFilter::exact('is_active'),
            )
            ->defaultSort('-is_head_office', 'name')
            ->allowedSorts(['code', 'name', 'city', 'is_head_office'])
            ->paginate(25)
            ->withQueryString();

        return view('accounting::branches.index', ['branches' => $branches]);
    }

    public function create(): View
    {
        abort_unless(CurrentBranch::enabled(), 404);

        return view('accounting::branches.form', ['branch' => null, 'members' => collect(), 'users' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $request->merge(['is_head_office' => $request->boolean('is_head_office'), 'is_active' => $request->boolean('is_active')]);
        $branch = $this->service->create($request->validate($this->service->rules()));

        return to_route('accounting.branches.edit', $branch)->with('success', "Branch {$branch->name} created. Give people access below if they should work in this branch only.");
    }

    public function edit(Branch $branch): View
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $users = config('auth.providers.users.model');
        $members = $this->service->members($branch);

        return view('accounting::branches.form', [
            'branch' => $branch,
            'members' => $members,
            'users' => $users::query()->whereNotIn('id', $members->pluck('id'))->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $request->merge(['is_head_office' => $request->boolean('is_head_office'), 'is_active' => $request->boolean('is_active')]);
        $this->service->update($branch, $request->validate($this->service->rules($branch)));

        return to_route('accounting.branches.index')->with('success', "Branch {$branch->name} updated.");
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);

        try {
            $this->service->delete($branch);
        } catch (AccountingException $exception) {
            return to_route('accounting.branches.index')->with('error', $exception->getMessage());
        }

        return to_route('accounting.branches.index')->with('success', "Branch {$branch->name} deleted.");
    }

    public function grant(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $users = config('auth.providers.users.model');

        $this->service->grant($branch, $users::query()->findOrFail($data['user_id']));

        return back()->with('success', 'Access given.');
    }

    public function revoke(Branch $branch, int $user): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $users = config('auth.providers.users.model');

        $this->service->revoke($branch, $users::query()->findOrFail($user));

        return back()->with('success', 'Access removed.');
    }
}
