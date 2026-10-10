<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Middleware;

use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A route of a feature that is switched off does not exist: 404 for the web screens and the API alike (the data stays where it is).
 */
class EnsureAccountingFeatureEnabled
{
    public function __construct(private readonly FeatureManager $features) {}

    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->route()?->uri();

        if ($uri === null) {
            return $next($request);
        }

        $path = $this->features->relative($uri);

        $feature = $this->features->forPath($path);

        if ($feature !== null && ! $this->features->enabled($feature)) {
            $label = FeatureManager::catalog()[$feature]['label'];

            if ($request->expectsJson()) {
                return response()->json(['message' => "The {$label} feature is turned off."], 404);
            }

            abort(404, "The {$label} feature is turned off.");
        }

        return $next($request);
    }
}
