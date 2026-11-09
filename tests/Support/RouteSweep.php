<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;

/**
 * Finds every GET route of the package and a URL to open it with: a route with parameters gets the first record of the model its
 * controller binds, and is left out when there is none yet.
 */
final class RouteSweep
{
    /**
     * @return list<string> the URLs, one per value a route accepts for a parameter limited to a few ("csv|xlsx|pdf")
     */
    public static function urls(string $prefix): array
    {
        $urls = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) && str_starts_with($route->uri(), $prefix)) {
                array_push($urls, ...self::resolve($route));
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<string>
     */
    private static function resolve(Route $route): array
    {
        $uris = [$route->uri()];

        foreach ($route->parameterNames() as $name) {
            $values = self::values($route, $name);

            if ($values === []) {
                return [];
            }

            $uris = collect($uris)->flatMap(fn (string $uri): array => array_map(
                fn (string|int $value): string => (string) preg_replace('/\{'.preg_quote($name, '/').'\??\}/', rawurlencode((string) $value), $uri),
                $values,
            ))->all();
        }

        return array_map(fn (string $uri): string => '/'.ltrim((string) preg_replace('/\{[^}]+\?\}/', '', $uri), '/'), $uris);
    }

    /**
     * @return list<string|int>
     */
    private static function values(Route $route, string $name): array
    {
        $pattern = $route->wheres[$name] ?? null;

        if (is_string($pattern) && preg_match('/^[A-Za-z0-9_-]+(\|[A-Za-z0-9_-]+)*$/', $pattern) === 1) {
            return explode('|', $pattern);
        }

        // Parameters that are a name rather than a record.
        $named = ['topic' => ['overview', 'getting-started'], 'report' => ['trial-balance'], 'template' => ['trading']];

        if (isset($named[$name]) && $route->getAction('controller') !== null && self::key($route, $name) === null) {
            return $named[$name];
        }

        $key = self::key($route, $name);

        return $key === null ? [] : [$key];
    }

    private static function key(Route $route, string $name): string|int|null
    {
        $controller = $route->getAction('controller');

        if (! is_string($controller)) {
            return null;
        }

        [$class, $method] = array_pad(explode('@', $controller), 2, '__invoke');

        if (! method_exists($class, $method)) {
            return null;
        }

        foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($parameter->getName() !== $name || ! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            if (is_subclass_of($type->getName(), Model::class)) {
                $model = $type->getName()::query()->withoutGlobalScopes()->first();

                return $model?->getKey();
            }
        }

        // The simple resources take an untyped {record}: the controller knows its model.
        if ($name === 'record' && method_exists($class, 'model')) {
            $model = (new ReflectionMethod($class, 'model'))->invoke(app($class));

            return is_subclass_of($model, Model::class) ? $model::query()->withoutGlobalScopes()->first()?->getKey() : null;
        }

        return null;
    }
}
