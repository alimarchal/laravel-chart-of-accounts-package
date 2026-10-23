<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the abilities of a Sanctum token when accounting.api_enforce_token_abilities is on: reading (GET, HEAD, OPTIONS) needs
 * `accounting:read`, anything else `accounting:write` (which also reads). `read` / `write` and a token with `*` work too. A request
 * that is not authenticated with a token (session, another guard) is left to the permission checks.
 */
class EnsureAccountingTokenAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('accounting.api_enforce_token_abilities', false)) {
            return $next($request);
        }

        $user = $request->user();
        $token = $user !== null && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token === null || ! method_exists($token, 'can')) {
            return $next($request);
        }

        $reads = in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true);
        $allowed = $reads
            ? ['accounting:read', 'accounting:write', 'read', 'write']
            : ['accounting:write', 'write'];

        foreach ($allowed as $ability) {
            if ($token->can($ability)) {
                return $next($request);
            }
        }

        abort(403, $reads
            ? 'This token cannot read accounting data: it needs the accounting:read ability.'
            : 'This token cannot change accounting data: it needs the accounting:write ability.');
    }
}
