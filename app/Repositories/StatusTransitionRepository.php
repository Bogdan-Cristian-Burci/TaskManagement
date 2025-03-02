<?php

namespace App\Repositories;

use App\Models\StatusTransition;
use App\Repositories\Interfaces\StatusTransitionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class StatusTransitionRepository implements StatusTransitionRepositoryInterface
{
    /**
     * @var StatusTransition
     */
    protected StatusTransition $model;

    /**
     * Cache time in seconds (1 day)
     *
     * @var int
     */
    protected int $cacheTime = 86400;

    /**
     * @param StatusTransition $statusTransition
     */
    public function __construct(StatusTransition $statusTransition)
    {
        $this->model = $statusTransition;
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        return Cache::remember('status_transitions:all', $this->cacheTime, function () use ($columns) {
            return $this->model->with(['fromStatus', 'toStatus'])->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->with(['fromStatus', 'toStatus'])->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        return Cache::remember("status_transitions:id:{$id}", $this->cacheTime, function () use ($id, $columns) {
            return $this->model->with(['fromStatus', 'toStatus'])->find($id, $columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return Cache::remember("status_transitions:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->with(['fromStatus', 'toStatus'])->where($field, $value)->first($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        return Cache::remember("status_transitions:all:{$field}:{$value}", $this->cacheTime, function () use ($field, $value, $columns) {
            return $this->model->with(['fromStatus', 'toStatus'])->where($field, $value)->get($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes): Model
    {
        $statusTransition = $this->model->create($attributes);
        $this->clearCache();

        return $statusTransition;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $model, array $attributes): bool
    {
        $updated = $model->update($attributes);

        if ($updated) {
            $this->clearCache();
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
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function findForBoard(int $boardId): Collection
    {
        return Cache::remember("status_transitions:board:{$boardId}", $this->cacheTime, function () use ($boardId) {
            return $this->model->with(['fromStatus', 'toStatus'])
                ->where('board_id', $boardId)
                ->orWhereNull('board_id')
                ->get();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findBetweenStatuses(int $fromStatusId, int $toStatusId, ?int $boardId = null): ?StatusTransition
    {
        $cacheKey = "status_transitions:from:{$fromStatusId}:to:{$toStatusId}";
        if ($boardId !== null) {
            $cacheKey .= ":board:{$boardId}";
        }

        return Cache::remember($cacheKey, $this->cacheTime, function () use ($fromStatusId, $toStatusId, $boardId) {
            $query = $this->model->where('from_status_id', $fromStatusId)
                ->where('to_status_id', $toStatusId);

            if ($boardId !== null) {
                $query->where(function ($q) use ($boardId) {
                    $q->where('board_id', $boardId)->orWhereNull('board_id');
                });
            }

            return $query->first();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findFromStatus(int $fromStatusId, ?int $boardId = null): Collection
    {
        $cacheKey = "status_transitions:from:{$fromStatusId}";
        if ($boardId !== null) {
            $cacheKey .= ":board:{$boardId}";
        }

        return Cache::remember($cacheKey, $this->cacheTime, function () use ($fromStatusId, $boardId) {
            $query = $this->model->with(['toStatus'])
                ->where('from_status_id', $fromStatusId);

            if ($boardId !== null) {
                $query->where(function ($q) use ($boardId) {
                    $q->where('board_id', $boardId)->orWhereNull('board_id');
                });
            }

            return $query->get();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        Cache::forget('status_transitions:all');

        // Clear board-specific caches
        $boardIds = $this->model->distinct()->pluck('board_id')->filter();
        foreach ($boardIds as $boardId) {
            Cache::forget("status_transitions:board:{$boardId}");
        }

        // Clear transition caches
        $transitions = $this->model->select(['id', 'from_status_id', 'to_status_id', 'board_id'])->get();
        foreach ($transitions as $transition) {
            Cache::forget("status_transitions:id:{$transition->id}");

            $cacheKey = "status_transitions:from:{$transition->from_status_id}:to:{$transition->to_status_id}";
            if ($transition->board_id !== null) {
                Cache::forget($cacheKey . ":board:{$transition->board_id}");
            }
            Cache::forget($cacheKey);

            Cache::forget("status_transitions:from:{$transition->from_status_id}");
            if ($transition->board_id !== null) {
                Cache::forget("status_transitions:from:{$transition->from_status_id}:board:{$transition->board_id}");
            }
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
