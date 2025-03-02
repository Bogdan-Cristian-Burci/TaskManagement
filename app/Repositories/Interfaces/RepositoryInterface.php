<?php

namespace App\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface RepositoryInterface
 *
 * Base repository interface defining common methods for data access.
 *
 * @package App\Repositories\Interfaces
 */
interface RepositoryInterface
{
    /**
     * Get all records.
     *
     * @param array $columns Columns to select
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated records.
     *
     * @param int $perPage Number of items per page
     * @param array $columns Columns to select
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Find a record by its ID.
     *
     * @param int $id Record ID
     * @param array $columns Columns to select
     * @return Model|null
     */
    public function find(int $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by a specific field.
     *
     * @param string $field Field to search by
     * @param mixed $value Value to search for
     * @param array $columns Columns to select
     * @return Model|null
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model;

    /**
     * Find multiple records by a specific field.
     *
     * @param string $field Field to search by
     * @param mixed $value Value to search for
     * @param array $columns Columns to select
     * @return Collection
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection;

    /**
     * Create a new record.
     *
     * @param array $attributes Record attributes
     * @return Model
     */
    public function create(array $attributes): Model;

    /**
     * Update a record.
     *
     * @param Model $model Model instance
     * @param array $attributes Updated attributes
     * @return bool
     */
    public function update(Model $model, array $attributes): bool;

    /**
     * Delete a record.
     *
     * @param Model $model Model instance
     * @return bool
     */
    public function delete(Model $model): bool;

    /**
     * Clear cache related to this repository.
     *
     * @return void
     */
    public function clearCache(): void;

    /**
     * Get the model instance.
     *
     * @return Model
     */
    public function getModel(): Model;
}
