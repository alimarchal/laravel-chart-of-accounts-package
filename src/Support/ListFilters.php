<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * One place that turns request parameters into list filters for every screen and endpoint,
 * on top of spatie/laravel-query-builder.
 *
 * Standard form (API and screens):  ?filter[status]=draft&filter[search]=rent&sort=-date
 * Short form (kept for the screens): ?status=draft&search=rent
 * The short form is read only for filters a list allows, and `filter[...]` wins when both are sent.
 *
 *   ListFilters::for(Party::query(), $request, [
 *       ListFilters::search('search', ['name', 'code']),
 *       ListFilters::exact('type'),
 *       ListFilters::bool('active', 'is_active'),
 *   ], sorts: ['name', 'code'], defaultSort: 'name')->get();
 */
final class ListFilters
{
    /**
     * @param  Builder<*>|Relation<*, *, *>  $query
     * @param  list<AllowedFilter>  $filters
     * @param  list<string>  $sorts
     * @param  array<string, string>  $aliases  short parameter => filter name, when they differ
     */
    public static function for(Builder|Relation $query, Request $request, array $filters, array $sorts = [], ?string $defaultSort = null, array $aliases = []): QueryBuilder
    {
        $builder = QueryBuilder::for($query, self::normalise($request, $filters, $aliases))
            ->allowedFilters(...$filters);

        if ($sorts !== []) {
            $builder->allowedSorts(...$sorts);
        }

        if ($defaultSort !== null && $request->query('sort') === null) {
            $descending = str_starts_with($defaultSort, '-');
            $builder->orderBy(ltrim($defaultSort, '-'), $descending ? 'desc' : 'asc');
        }

        return $builder;
    }

    /**
     * The filters in effect (standard or short form) for the screens to show in their filter bars.
     *
     * @param  list<string>  $names
     * @param  array<string, string>  $aliases
     * @return array<string, mixed>
     */
    public static function current(Request $request, array $names, array $aliases = []): array
    {
        $given = self::given($request, $names, $aliases);

        return collect($names)->mapWithKeys(fn (string $name) => [$name => $given[$name] ?? null])->all();
    }

    /**
     * Free-text search over several columns (any word part, % and _ are matched literally).
     *
     * @param  list<string>  $columns
     * @param  array<string, list<string>>  $relations  relation => columns searched on the related rows too
     */
    public static function search(string $name, array $columns, array $relations = []): AllowedFilter
    {
        return AllowedFilter::callback($name, function ($query, $value) use ($columns, $relations): void {
            $term = self::like(self::text($value));

            if ($term === '') {
                return;
            }

            $grammar = $query->getQuery()->getGrammar();

            $query->where(function ($inner) use ($columns, $relations, $term, $grammar): void {
                foreach ($columns as $column) {
                    $inner->orWhereRaw($grammar->wrap($column)." like ? escape '!'", ["%{$term}%"]);
                }

                foreach ($relations as $relation => $related) {
                    $inner->orWhereHas($relation, function ($sub) use ($related, $term): void {
                        $sub->where(function ($match) use ($related, $term): void {
                            $grammar = $match->getQuery()->getGrammar();

                            foreach ($related as $column) {
                                $match->orWhereRaw($grammar->wrap($column)." like ? escape '!'", ["%{$term}%"]);
                            }
                        });
                    });
                }
            });
        });
    }

    /** One column equals the value; several values separated by commas match any of them. */
    public static function exact(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::exact($name, $column);
    }

    /** A yes/no column ("1", "true", "yes", "on" and their opposites). */
    public static function bool(string $name, ?string $column = null): AllowedFilter
    {
        $column ??= $name;

        return AllowedFilter::callback($name, function ($query, $value) use ($column): void {
            $flag = filter_var(is_array($value) ? ($value[0] ?? null) : $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($flag !== null) {
                $query->where($column, $flag);
            }
        });
    }

    /** A date column on or after the value. */
    public static function from(string $name, string $column): AllowedFilter
    {
        return AllowedFilter::callback($name, fn ($query, $value) => $query->whereDate($column, '>=', self::date($name, $value)));
    }

    /** A date column on or before the value. */
    public static function to(string $name, string $column): AllowedFilter
    {
        return AllowedFilter::callback($name, fn ($query, $value) => $query->whereDate($column, '<=', self::date($name, $value)));
    }

    /** A number column at or above the value. */
    public static function min(string $name, string $column): AllowedFilter
    {
        return AllowedFilter::callback($name, fn ($query, $value) => $query->where($column, '>=', self::number($name, $value)));
    }

    /** A number column at or below the value. */
    public static function max(string $name, string $column): AllowedFilter
    {
        return AllowedFilter::callback($name, fn ($query, $value) => $query->where($column, '<=', self::number($name, $value)));
    }

    /**
     * Anything the other helpers do not cover; the closure receives the query and the text value.
     *
     * @param  \Closure(mixed, string): mixed  $apply
     */
    public static function custom(string $name, \Closure $apply): AllowedFilter
    {
        return AllowedFilter::callback($name, function ($query, $value) use ($apply): void {
            $text = self::text($value);

            if ($text !== '') {
                $apply($query, $text);
            }
        });
    }

    /**
     * @param  list<AllowedFilter>  $filters
     * @param  array<string, string>  $aliases
     */
    private static function normalise(Request $request, array $filters, array $aliases): Request
    {
        $names = array_map(fn (AllowedFilter $filter) => $filter->getName(), $filters);
        $given = self::given($request, $names, $aliases);

        $query = $request->query();
        $others = array_diff_key((array) ($query['filter'] ?? []), array_flip($names));
        $query['filter'] = $others + $given;

        if ($query['filter'] === []) {
            unset($query['filter']);
        }

        return $request->duplicate($query);
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, string>  $aliases
     * @return array<string, mixed>
     */
    private static function given(Request $request, array $names, array $aliases): array
    {
        $standard = (array) $request->query('filter', []);
        $short = array_flip($aliases);
        $given = [];

        foreach ($names as $name) {
            $value = $standard[$name] ?? $request->query($short[$name] ?? $name);

            if ($value !== null && $value !== '' && $value !== []) {
                $given[$name] = $value;
            }
        }

        return $given;
    }

    private static function text(mixed $value): string
    {
        return trim(is_array($value) ? implode(',', array_map('strval', $value)) : (string) $value);
    }

    private static function like(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    private static function date(string $name, mixed $value): string
    {
        $text = self::text($value);
        Validator::make([$name => $text], [$name => ['date']])->validate();

        return $text;
    }

    private static function number(string $name, mixed $value): string
    {
        $text = self::text($value);
        Validator::make([$name => $text], [$name => ['numeric']])->validate();

        return $text;
    }
}
