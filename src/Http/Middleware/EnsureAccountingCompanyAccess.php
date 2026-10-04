<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Middleware;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With multi-company enabled, every accounting request must work in a company the user may access:
 * an unknown X-Company is 404, a company the user is not assigned to is 403. A session pointing to a
 * company the user lost access to falls back to one they can use.
 */
class EnsureAccountingCompanyAccess
{
    public function __construct(private readonly CurrentCompany $companies) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! CurrentCompany::enabled()) {
            return $next($request);
        }

        try {
            $company = $this->companies->get();
        } catch (AccountingException $exception) {
            abort(404, $exception->getMessage());
        }

        if (! $this->companies->canAccess($request->user(), $company)) {
            $fromSession = ! $request->hasHeader((string) config('accounting.multi_company.header', 'X-Company'))
                && $request->hasSession() && $request->session()->has(CurrentCompany::SESSION_KEY);

            if ($fromSession) {
                $request->session()->forget(CurrentCompany::SESSION_KEY);
                $this->companies->forget();
                $company = $this->companies->get();
            }

            abort_unless($this->companies->canAccess($request->user(), $company), 403, 'You do not have access to this company.');
        }

        return $next($request);
    }
}
