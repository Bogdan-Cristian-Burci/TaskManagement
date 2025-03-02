<?php

namespace App\Repositories;

use App\Models\ChangeType;
use App\Repositories\Interfaces\ChangeTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ChangeTypeRepository implements ChangeTypeRepositoryInterface
{
    /**
     * @var ChangeType
     */
    protected ChangeType $model;

    /**
     * Cache time in seconds (1 day)
     *
     * @var int
     */
    protected int $cacheTime = 86400;

    /**
     * @param ChangeType $changeType
     */
    public function __construct(ChangeType $changeType)
    {
        $this->model = $changeType;
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        return Cache::remember('change_types:all', $this->cacheTime, function () use ($columns) {
            return $this->model->all($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        return Cache::remember("change_types:id:{$id}", $this->cacheTime, function () use ($id, $columns) {
            return $this->model->find($id, $columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return Cache::remember("change_types:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->first($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        return Cache::remember("change_types:all:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes): Model
    {
        $changeType = $this->model->create($attributes);
        $this->clearCache();

        return $changeType;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $attributes): bool
    {
        $updated = $model->update($attributes);

        if ($updated) {
            $this->clearCache();
            Cache::forget("change_types:id:{$model->id}");

            // Also clear cache for this change type's name
            Cache::forget("change_types:name:{$model->name}");
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
            Cache::forget("change_types:id:{$model->id}");
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(string $name): ?ChangeType
    {
        return Cache::remember("change_types:name:{$name}", $this->cacheTime, function () use ($name) {
            return $this->model->where('name', $name)->first();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findAllByPartialName(string $name): Collection
    {
        // Don't cache partial name searches as they can be too numerous
        return $this->model->where('name', 'like', "%{$name}%")->get();
    }

    /**
     * {@inheritdoc}
     */
    public function findWithTaskHistoriesCount(): Collection
    {
        return Cache::remember("change_types:with_task_histories_count", $this->cacheTime, function () {
            return $this->model->withCount(['taskHistories', 'taskHistoriesByName'])
                ->get()
                ->map(function ($changeType) {
                    // Combine both relationship counts
                    $changeType->task_histories_count =
                        $changeType->task_histories_count + $changeType->task_histories_by_name_count;
                    return $changeType;
                });
        });
    }


    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        // Clear collection caches
        Cache::forget('change_types:all');
        Cache::forget('change_types:with_task_histories_count');

        // Clear individual change type caches
        $changeTypes = $this->model->all(['id', 'name']);
        foreach ($changeTypes as $changeType) {
            Cache::forget("change_types:id:{$changeType->id}");
            Cache::forget("change_types:name:{$changeType->name}");
        }

        // Consider using cache tags if your cache driver supports them
        // Cache::tags(['change_types'])->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
