<?php

namespace App\Repositories;

use App\Models\TaskType;
use App\Repositories\Interfaces\TaskTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class TaskTypeRepository implements TaskTypeRepositoryInterface
{
    /**
     * @var TaskType
     */
    protected TaskType $model;

    /**
     * Cache time in seconds (1 day)
     *
     * @var int
     */
    protected int $cacheTime = 86400;

    /**
     * @param TaskType $taskType
     */
    public function __construct(TaskType $taskType)
    {
        $this->model = $taskType;
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        return Cache::remember('task_types:all', $this->cacheTime, function () use ($columns) {
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
        return Cache::remember("task_types:id:{$id}", $this->cacheTime, function () use ($id, $columns) {
            return $this->model->find($id, $columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return Cache::remember("task_types:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->first($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        return Cache::remember("task_types:all:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes): Model
    {
        $taskType = $this->model->create($attributes);
        $this->clearCache();

        return $taskType;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $attributes): bool
    {
        $updated = $model->update($attributes);

        if ($updated) {
            $this->clearCache();
            Cache::forget("task_types:id:{$model->id}");
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
            Cache::forget("task_types:id:{$model->id}");
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function findByName(string $name): ?TaskType
    {
        return Cache::remember("task_types:name:{$name}", $this->cacheTime, function () use ($name) {
            return $this->model->where('name', $name)->first();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getWithTaskCount(): Collection
    {
        return Cache::remember('task_types:with_tasks_count', $this->cacheTime, function () {
            return $this->model->withCount('tasks')->get();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        Cache::forget('task_types:all');
        Cache::forget('task_types:with_tasks_count');

        $taskTypes = $this->model->all('id', 'name');
        foreach ($taskTypes as $taskType) {
            Cache::forget("task_types:id:{$taskType->id}");
            Cache::forget("task_types:name:{$taskType->name}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
