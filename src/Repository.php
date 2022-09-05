<?php

declare(strict_types=1);

namespace Gorczakw\LaravelRepository;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class Repository
{
    use HasFilters;

    protected string $model;

    public function __construct()
    {
        $this->model = $this->getModel();
    }

    abstract public function getModel(): string;

    public function getObject(): Model
    {
        return new $this->model;
    }

    public function getBuilder(): Builder
    {
        return $this->getObject()->newQuery();
    }

    public function single(int|string $id): ?Model
    {
        return $this->getBuilder()->find($id);
    }

    public function all(?array $requestData = []): Builder
    {
        $builder = $this->getBuilder();
        $filters = $this->getFiltersFromRequestData($requestData ?? []);
        $this->applyFilters($builder, $filters);

        return $builder;
    }

    public function getAll(?array $requestData = []): Collection
    {
        return $this->all($requestData)->get();
    }

    protected function getFiltersFromRequestData($requestData): array
    {
        $filters = $requestData['filters'] ?? [];
        if (array_key_exists('search', $requestData)) {
            $filters['search'] = $requestData['search'];
        }

        return $filters;
    }

    public function random(): ?Model
    {
        return $this->getBuilder()->inRandomOrder()->first();
    }

    public function randomMany(int $number = 5): Collection
    {
        return $this->getBuilder()->inRandomOrder()->limit($number)->get();
    }

    public function create(array $data): ?Model
    {
        $model = new $this->model;
        $model->fill($data);
        $model->save();

        return $model;
    }

    public function update(array $data, mixed $modelOrPrimaryKey): ?Model
    {
        $model = $this->modelOrPrimaryKey($modelOrPrimaryKey);

        if (!$model instanceof Model) {
            return null;
        }

        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * @throws LogicException
     */
    public function destroy(mixed $modelOrPrimaryKey): bool
    {
        $model = $this->modelOrPrimaryKey($modelOrPrimaryKey);

        if (!$model instanceof Model) {
            return false;
        }

        return $model->delete();
    }

    public function modelOrPrimaryKey(Model|int|string $modelOrPrimaryKey): ?Model
    {
        if (!$modelOrPrimaryKey instanceof Model) {
            return $this->single($modelOrPrimaryKey);
        }

        return $modelOrPrimaryKey;
    }
}
