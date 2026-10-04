<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

abstract class SimpleAccountingResourceController extends Controller
{
    abstract protected function model(): string;

    abstract protected function routeName(): string;

    abstract protected function title(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract protected function fields(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function rules(?Model $record = null): array;

    public function index(): Response
    {
        /** @var class-string<Model> $model */
        $model = $this->model();

        return Inertia::render('accounting/resources/index', [
            'title' => $this->title(),
            'routeName' => $this->routeName(),
            'records' => QueryBuilder::for($model::query())
                ->allowedFilters(...$this->allowedFilters())
                ->defaultSort('-id')
                ->paginate(25)
                ->withQueryString(),
            'columns' => collect($this->fields())
                ->where('table', true)
                ->pluck('name')
                ->prepend('id')
                ->values(),
            'fields' => $this->fields(),
            'filters' => request()->input('filter', []),
            'readOnly' => $this->readOnly(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounting/resources/form', [
            'title' => 'Create '.$this->title(),
            'routeName' => $this->routeName(),
            'fields' => $this->fields(),
            'record' => null,
            'method' => 'post',
            'action' => route(config('accounting.route_name_prefix', 'settings').'.'.$this->routeName().'.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var class-string<Model> $model */
        $model = $this->model();

        $this->normalizeCheckboxes($request);

        $record = $this->persistCreate($request->validate($this->rules()));

        return to_route(config('accounting.route_name_prefix', 'settings').'.'.$this->routeName().'.index')
            ->with('success', $this->title().' created: '.$record->getKey());
    }

    public function edit(int|string $record): Response
    {
        $record = $this->findRecord($record);

        return Inertia::render('accounting/resources/form', [
            'title' => 'Edit '.$this->title(),
            'routeName' => $this->routeName(),
            'fields' => $this->fields(),
            'record' => $record,
            'method' => 'put',
            'action' => route(config('accounting.route_name_prefix', 'settings').'.'.$this->routeName().'.update', $record),
        ]);
    }

    public function show(int|string $record): Response
    {
        $record = $this->findRecord($record);

        return Inertia::render('accounting/resources/show', [
            'title' => $this->title(),
            'routeName' => $this->routeName(),
            'fields' => $this->fields(),
            'record' => $record,
        ]);
    }

    public function update(Request $request, int|string $record): RedirectResponse
    {
        $record = $this->findRecord($record);

        $this->normalizeCheckboxes($request);

        $record = $this->persistUpdate($record, $request->validate($this->rules($record)));

        return to_route(config('accounting.route_name_prefix', 'settings').'.'.$this->routeName().'.index')
            ->with('success', $this->title().' updated: '.$record->getKey());
    }

    public function destroy(int|string $record): RedirectResponse
    {
        $record = $this->findRecord($record);
        $this->persistDelete($record);

        return to_route(config('accounting.route_name_prefix', 'settings').'.'.$this->routeName().'.index')
            ->with('success', $this->title().' deleted: '.$record->getKey());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistCreate(array $data): Model
    {
        /** @var class-string<Model> $model */
        $model = $this->model();

        return $model::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    protected function persistDelete(Model $record): void
    {
        try {
            $record->delete();
        } catch (QueryException $exception) { // @phpstan-ignore catch.neverThrown (delete() can violate a foreign key)
            // Foreign-key violation (SQLSTATE 23000 / 23503): the record is still referenced.
            if (in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw new AccountingException('This record is in use by other accounting records and cannot be deleted.');
            }

            throw $exception;
        }
    }

    protected function findRecord(int|string $record): Model
    {
        /** @var class-string<Model> $model */
        $model = $this->model();

        return $model::query()->findOrFail($record);
    }

    protected function readOnly(): bool
    {
        return false;
    }

    private function normalizeCheckboxes(Request $request): void
    {
        $checkboxes = collect($this->fields())
            ->where('type', 'checkbox')
            ->pluck('name')
            ->mapWithKeys(fn (string $field): array => [$field => $request->boolean($field)])
            ->all();

        $request->merge($checkboxes);
    }

    /**
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(): array
    {
        return collect($this->fields())
            ->filter(fn (array $field): bool => $field['filter'] ?? $field['table'] ?? false)
            ->map(function (array $field): AllowedFilter {
                return in_array($field['type'], ['checkbox', 'select', 'number', 'date'], true)
                    ? AllowedFilter::exact($field['name'])
                    : AllowedFilter::partial($field['name']);
            })
            ->values()
            ->all();
    }
}
