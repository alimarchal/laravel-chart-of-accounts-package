<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

abstract class SimpleAccountingApiController extends Controller
{
    abstract protected function model(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function rules(?Model $record = null): array;

    public function index(): ResourceCollection
    {
        /** @var class-string<Model> $model */
        $model = $this->model();

        return JsonResource::collection(
            QueryBuilder::for($model::query())
                ->allowedFilters(...$this->allowedFilters())
                ->defaultSort('-id')
                ->paginate()
                ->withQueryString()
        );
    }

    public function store(Request $request): JsonResource
    {
        /** @var class-string<Model> $model */
        $model = $this->model();

        $record = $this->persistCreate($request->validate($this->rules()));

        return JsonResource::make($record->refresh());
    }

    public function show(int|string $record): JsonResource
    {
        return JsonResource::make($this->findRecord($record));
    }

    public function update(Request $request, int|string $record): JsonResource
    {
        $record = $this->findRecord($record);
        $record = $this->persistUpdate($record, $request->validate($this->rules($record)));

        return JsonResource::make($record->refresh());
    }

    public function destroy(int|string $record): JsonResponse
    {
        $this->persistDelete($this->findRecord($record));

        return response()->json(null, 204);
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
        } catch (QueryException $exception) {
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

    /**
     * @return array<int, AllowedFilter>
     */
    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::partial('code'),
            AllowedFilter::partial('name'),
            AllowedFilter::partial('description'),
            AllowedFilter::exact('is_active'),
        ];
    }
}
