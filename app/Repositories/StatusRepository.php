<?php

namespace App\Repositories;

use App\Models\Status;
use App\Repositories\Interfaces\StatusRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusRepository implements StatusRepositoryInterface
{
    /**
     * @var Status
     */
    protected Status $model;

    /**
     * Cache time in seconds (1 day)
     *
     * @var int
     */
    protected int $cacheTime = 86400;

    /**
     * @param Status $status
     */
    public function __construct(Status $status)
    {
        $this->model = $status;
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        return Cache::remember('statuses:all', $this->cacheTime, function () use ($columns) {
            return $this->model->orderBy('position')->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->orderBy('position')->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        return Cache::remember("statuses:id:{$id}", $this->cacheTime, function () use ($id, $columns) {
            return $this->model->find($id, $columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return Cache::remember("statuses:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->first($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        return Cache::remember("statuses:all:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->orderBy('position')->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes): Model
    {
        // Set position if not provided
        if (!isset($attributes['position'])) {
            $maxPosition = $this->model->max('position') ?? 0;
            $attributes['position'] = $maxPosition + 1;
        }

        $status = $this->model->create($attributes);
        $this->clearCache();

        return $status;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $attributes): bool
    {
        $updated = $model->update($attributes);

        if ($updated) {
            $this->clearCache();
            Cache::forget("statuses:id:{$model->id}");
        }

        return $updated;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Model $model): bool
    {
        $deleted = $model->delete();

        if ($deleted) {
            $this->clearCache();
            Cache::forget("statuses:id:{$model->id}");
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(string $name): ?Status
    {
        return Cache::remember("statuses:name:{$name}", $this->cacheTime, function () use ($name) {
            return $this->model->where('name', $name)->first();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getDefault(): ?Status
    {
        return Cache::remember('statuses:default', $this->cacheTime, function () {
            return $this->model->where('is_default', true)->first();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        Cache::forget('statuses:all');
        Cache::forget('statuses:default');

        // Clear category caches
        $categories = ['todo', 'in_progress', 'done', 'canceled'];
        foreach ($categories as $category) {
            Cache::forget("statuses:all:category:{$category}");
        }

        // Clear individual status caches
        $statusIds = $this->model->pluck('id')->all();
        foreach ($statusIds as $id) {
            Cache::forget("statuses:id:{$id}");
        }

    }

    /**
     * {@inheritdoc}
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * {@inheritdoc}
     */
    public function getByCategory(string $category): Collection
    {
        return Cache::remember("statuses:category:{$category}", $this->cacheTime, function () use ($category) {
            return $this->model->where('category', $category)->orderBy('position')->get();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function reorder(array $ids): bool
    {
        try {
            DB::beginTransaction();

            foreach ($ids as $index => $id) {
                $status = $this->find($id);
                if ($status) {
                    $status->update(['position' => $index + 1]);
                }
            }

            DB::commit();
            $this->clearCache();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }
}
