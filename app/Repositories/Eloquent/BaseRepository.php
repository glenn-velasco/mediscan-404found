<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 *
 * @implements BaseRepositoryContract<TModel>
 */
abstract class BaseRepository implements BaseRepositoryContract
{
    /** @var TModel */
    protected Model $model;

    /** @param  TModel  $model */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /** @return TModel|null */
    public function find(int $id): ?Model
    {
        return $this->model->query()->find($id);
    }

    /** @return TModel */
    public function findOrFail(int $id): Model
    {
        return $this->model->query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->model->query()->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        return $this->model->query()->create($data);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    /** @param  TModel  $model */
    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
