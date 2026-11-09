<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests\Support;

use Alimarchal\LaravelChartOfAccounts\Reports\ReportCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Orchestra\Testbench\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Opens the GET routes of the package and reports the ones that fail. A route with parameters gets the first record of the model its
 * controller binds, a parameter limited to a few values ("csv|xlsx|pdf") gets each of them; a route that cannot be given a record is
 * listed by skipped(), and the tests name those routes, so a new route that the sweep cannot reach fails them.
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
     * The URIs of the GET routes under a prefix that have no URL to open, because no record exists for one of their parameters.
     *
     * @return list<string>
     */
    public static function skipped(string $prefix): array
    {
        $skipped = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) && str_starts_with($route->uri(), $prefix) && self::resolve($route) === []) {
                $skipped[] = $route->uri();
            }
        }

        sort($skipped);

        return $skipped;
    }

    /**
     * Opens every URL, each in a savepoint of its own (a failed statement must not poison the rest on PostgreSQL, and a request with
     * side effects must not leak into the next), runs the body of a streamed download (a CSV export is only built when it is read),
     * and lists the URLs that answered 5xx or threw. A 4xx is a legitimate answer to a bare GET (a feature switched off, a document that is no
     * longer a draft, a preview that needs its parameters, nothing to export yet) and is not a failure.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    public static function failures(TestCase $test, array $urls, bool $json = false): array
    {
        $failures = [];

        foreach ($urls as $url) {
            DB::beginTransaction();

            try {
                $response = $json ? $test->getJson($url) : $test->get($url);

                if ($response->baseResponse instanceof StreamedResponse) {
                    $response->streamedContent();
                }

                if ($response->getStatusCode() >= 500) {
                    $failures[] = $response->getStatusCode().' '.$url.' '.($response->exception?->getMessage() ?? '');
                }
            } catch (Throwable $exception) {
                $failures[] = 'threw '.$url.' '.$exception->getMessage();
            } finally {
                DB::rollBack();
            }
        }

        return $failures;
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
                fn (string|int $value): string => (string) preg_replace('/\{'.preg_quote($name, '/').'\}/', rawurlencode((string) $value), $uri),
                $values,
            ))->all();
        }

        return array_map(fn (string $uri): string => '/'.ltrim($uri, '/'), $uris);
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
        $named = [
            'topic' => ['overview', 'getting-started'],
            'template' => ['trading'],
            'report' => str_contains($route->uri(), 'payroll/') ? ['comparison', 'cost-centers', 'headcount'] : app(ReportCatalog::class)->names(),
        ];

        $key = self::key($route, $name);

        if ($key === null) {
            return $named[$name] ?? [];
        }

        return [$key];
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

        // The user screens take the key of a user untyped.
        if ($name === 'user') {
            return app(config('auth.providers.users.model'))::query()->first()?->getKey();
        }

        // The simple resources take an untyped {record}: the controller knows its model.
        if ($name === 'record' && method_exists($class, 'model')) {
            $model = (new ReflectionMethod($class, 'model'))->invoke(app($class));

            return is_subclass_of($model, Model::class) ? $model::query()->withoutGlobalScopes()->first()?->getKey() : null;
        }

        return null;
    }
}
