<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

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

        $record = $model::query()->create($request->validate($this->rules()));

        return JsonResource::make($record->refresh());
    }

    public function show(int|string $record): JsonResource
    {
        return JsonResource::make($this->findRecord($record));
    }

    public function update(Request $request, int|string $record): JsonResource
    {
        $record = $this->findRecord($record);
        $record->update($request->validate($this->rules($record)));

        return JsonResource::make($record->refresh());
    }

    public function destroy(int|string $record): JsonResponse
    {
        $this->findRecord($record)->delete();

        return response()->json(null, 204);
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
