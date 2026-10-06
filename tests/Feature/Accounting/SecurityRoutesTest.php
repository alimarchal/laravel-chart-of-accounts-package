<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Every route the package registers must say who may call it: a "can:" ability on top of authentication. A route added later
 * without one (a forgotten middleware) is the commonest way for a screen or an endpoint to end up open to every signed-in user.
 */
function packageRoutes(): array
{
    $names = ['accounting.', 'api.accounting.', 'settings.'];
    $uris = [trim((string) config('accounting.route_prefix', 'accounting'), '/').'/', trim((string) config('accounting.api_prefix', 'api/v1/accounting'), '/').'/', trim((string) config('accounting.settings_route_prefix', 'settings'), '/').'/'];

    // By name and by address, so a route that was given no name cannot slip through.
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => collect($names)->contains(fn (string $prefix) => str_starts_with((string) $route->getName(), $prefix))
            || collect($uris)->contains(fn (string $prefix) => str_starts_with($route->uri().'/', $prefix) || $route->uri() === rtrim($prefix, '/')))
        ->values()
        ->all();
}

it('puts an ability check on every route of the package', function (): void {
    $open = collect(packageRoutes())->reject(function (Route $route): bool {
        return collect($route->gatherMiddleware())->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'));
    })->map(fn (Route $route) => implode('|', $route->methods()).' /'.$route->uri().' ['.$route->getName().']')->values()->all();

    expect($open)->toBe([]);
})->skip(fn () => count(packageRoutes()) === 0, 'No routes.');

it('requires authentication on every route of the package', function (): void {
    $anonymous = collect(packageRoutes())->reject(function (Route $route): bool {
        return collect($route->gatherMiddleware())->contains(fn ($middleware) => is_string($middleware) && (str_starts_with($middleware, 'auth')));
    })->map(fn (Route $route) => '/'.$route->uri())->values()->all();

    expect($anonymous)->toBe([]);
});

it('keeps state-changing routes off GET', function (): void {
    $risky = collect(packageRoutes())->filter(function (Route $route): bool {
        $name = (string) $route->getName();

        return in_array('GET', $route->methods(), true) && (bool) preg_match('/\.(destroy|delete|post|void|approve|reject|reverse|close|reopen|pay|dispose|run|submit|store|update|sync|switch)$/', $name);
    })->map(fn (Route $route) => '/'.$route->uri().' ['.$route->getName().']')->values()->all();

    expect($risky)->toBe([]);
});
