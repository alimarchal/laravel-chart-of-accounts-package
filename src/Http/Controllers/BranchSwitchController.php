<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Web UI: choose the branch the session works in (React and Blade), or all of the user's branches (empty branch_id).
 */
class BranchSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(CurrentBranch::enabled(), 404);
        $data = $request->validate(['branch_id' => ['nullable', 'integer']]);

        if (empty($data['branch_id'])) {
            $request->session()->forget(CurrentBranch::SESSION_KEY);
            CurrentBranch::flush();

            return back()->with('success', 'Showing all your branches.');
        }

        $branch = Branch::query()->findOrFail($data['branch_id']);

        abort_unless($branch->is_active, 422, 'This branch is not active.');
        abort_unless(CurrentBranch::canAccess($request->user(), $branch), 403, 'You do not have access to this branch.');

        $request->session()->put(CurrentBranch::SESSION_KEY, $branch->getKey());
        CurrentBranch::flush();

        return back()->with('success', "Now working in {$branch->name}.");
    }
}
