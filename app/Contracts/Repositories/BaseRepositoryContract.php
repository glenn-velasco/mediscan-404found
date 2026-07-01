<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 */
interface BaseRepositoryContract
{
    /** @return TModel|null */
    public function find(int $id): ?Model;

    /** @return TModel */
    public function findOrFail(int $id): Model;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model;

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): bool;

    /** @param  TModel  $model */
    public function delete(Model $model): bool;
}
