<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Middleware;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With branches enabled, the working branch of a request must be one the user may use: an unknown X-Branch is 404, a branch the user is not
 * assigned to is 403 (or inactive: 422). A session pointing to a branch the user lost is dropped, so the user is back at "all my branches".
 */
class EnsureAccountingBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CurrentBranch::enabled()) {
            return $next($request);
        }

        try {
            $branch = CurrentBranch::requested($request);
        } catch (AccountingException $exception) {
            abort(404, $exception->getMessage());
        }

        if ($branch !== null) {
            $fromHeader = $request->hasHeader((string) config('accounting.branches.header', 'X-Branch'));

            if (! CurrentBranch::canAccess($request->user(), $branch) || ! $branch->is_active) {
                if (! $fromHeader && $request->hasSession()) {
                    $request->session()->forget(CurrentBranch::SESSION_KEY);

                    return $next($request);
                }

                abort(! $branch->is_active ? 422 : 403, ! $branch->is_active ? 'This branch is not active.' : 'You do not have access to this branch.');
            }
        }

        return $next($request);
    }
}
