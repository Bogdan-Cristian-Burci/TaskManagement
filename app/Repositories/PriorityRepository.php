<?php

namespace App\Repositories;

use App\Models\Priority;
use App\Repositories\Interfaces\PriorityRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PriorityRepository implements PriorityRepositoryInterface
{
    /**
     * @var Priority
     */
    protected Priority $model;

    /**
     * Cache time in seconds (1 day)
     *
     * @var int
     */
    protected int $cacheTime = 86400;

    /**
     * @param Priority $priority
     */
    public function __construct(Priority $priority)
    {
        $this->model = $priority;
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        return Cache::remember('priorities:all', $this->cacheTime, function () use ($columns) {
            return $this->model->orderBy('level')->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->orderBy('level')->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        return Cache::remember("priorities:id:{$id}", $this->cacheTime, function () use ($id, $columns) {
            return $this->model->find($id, $columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return Cache::remember("priorities:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->first($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        return Cache::remember("priorities:all:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->where($field, $value)->orderBy('level')->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes): Model
    {
        // Set level if not provided
        if (!isset($attributes['level'])) {
            $maxLevel = $this->model->max('level') ?? 0;
            $attributes['level'] = $maxLevel + 1;
        }

        $priority = $this->model->create($attributes);
        $this->clearCache();

        return $priority;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $attributes): bool
    {
        $updated = $model->update($attributes);

        if ($updated) {
            $this->clearCache();
            Cache::forget("priorities:id:{$model->id}");
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
            Cache::forget("priorities:id:{$model->id}");
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function findByLevel(int $level): ?Priority
    {
        return Cache::remember("priorities:level:{$level}", $this->cacheTime, function () use ($level) {
            return $this->model->where('level', $level)->first();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function reorder(array $priorityIds): bool
    {
        try {
            DB::beginTransaction();

            foreach ($priorityIds as $index => $id) {
                $this->model->where('id', $id)->update(['level' => $index + 1]);
            }

            DB::commit();
            $this->clearCache();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        Cache::forget('priorities:all');

        // Consider using cache tags if your cache driver supports them
        // Cache::tags(['priorities'])->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
