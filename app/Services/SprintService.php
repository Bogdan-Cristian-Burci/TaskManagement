<?php

namespace App\Services;

use App\Enums\SprintStatusEnum;
use App\Models\Board;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SprintService
{
    /**
     * Create a sprint for a board.
     *
     * @param Board $board
     * @param array $attributes
     * @return Sprint
     */
    public function createSprint(Board $board, array $attributes): Sprint
    {
        $attributes['board_id'] = $board->id;
        return Sprint::create($attributes);
    }

    /**
     * Update a sprint.
     *
     * @param Sprint $sprint
     * @param array $attributes
     * @return Sprint
     */
    public function updateSprint(Sprint $sprint, array $attributes): Sprint
    {
        $sprint->update($attributes);
        return $sprint->fresh();
    }

    /**
     * Start a sprint.
     *
     * @param Sprint $sprint
     * @return Sprint
     */
    public function startSprint(Sprint $sprint): Sprint
    {
        $sprint->start();
        return $sprint->fresh();
    }

    /**
     * Complete a sprint.
     *
     * @param Sprint $sprint
     * @param Sprint|null $moveIncompleteTo Optional sprint to move incomplete tasks to
     * @return Sprint
     */
    public function completeSprint(Sprint $sprint, ?Sprint $moveIncompleteTo = null): Sprint
    {
        DB::transaction(function() use ($sprint, $moveIncompleteTo) {
            if ($moveIncompleteTo) {
                $incompleteTasks = $sprint->tasks()
                    ->where('status', '!=', SprintStatusEnum::COMPLETED->value)
                    ->get();

                $taskIds = $incompleteTasks->pluck('id')->toArray();
                $sprint->tasks()->detach($taskIds);

                if (!empty($taskIds)) {
                    $moveIncompleteTo->tasks()->attach($taskIds);
                }
            }

            $sprint->complete();
        });

        return $sprint->fresh();
    }

    /**
     * Add tasks to a sprint.
     *
     * @param Sprint $sprint
     * @param array $taskIds
     * @return Collection
     */
    public function addTasksToSprint(Sprint $sprint, array $taskIds): Collection
    {
        $sprint->tasks()->syncWithoutDetaching($taskIds);
        return $sprint->tasks()->whereIn('tasks.id', $taskIds)->get();
    }

    /**
     * Remove tasks from a sprint.
     *
     * @param Sprint $sprint
     * @param array $taskIds
     * @return bool
     */
    public function removeTasksFromSprint(Sprint $sprint, array $taskIds): bool
    {
        return (bool) $sprint->tasks()->detach($taskIds);
    }

    /**
     * Get sprints for a board with optional filters.
     *
     * @param Board $board
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getBoardSprints(Board $board, array $filters = [], array $with = []): Collection
    {
        $query = $board->sprints();

        // Apply status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply active filter
        if (isset($filters['active']) && $filters['active']) {
            $query->active();
        }

        // Apply overdue filter
        if (isset($filters['overdue']) && $filters['overdue']) {
            $query->overdue();
        }

        // Apply sorting
        $sortColumn = $filters['sort'] ?? 'start_date';
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($sortColumn, $direction);

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get tasks for a sprint with optional filters.
     *
     * @param Sprint $sprint
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getSprintTasks(Sprint $sprint, array $filters = [], array $with = []): Collection
    {
        $query = $sprint->tasks();

        // Apply status filter
        if (isset($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        // Apply assignee filter
        if (isset($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        // Apply sorting
        $sortColumn = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($sortColumn, $direction);

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Check if a sprint can be deleted.
     *
     * @param Sprint $sprint
     * @return array
     */
    public function canDeleteSprint(Sprint $sprint): array
    {
        $taskCount = $sprint->tasks()->count();
        
        return [
            'can_delete' => $taskCount === 0,
            'task_count' => $taskCount
        ];
    }

    /**
     * Delete a sprint.
     *
     * @param Sprint $sprint
     * @return bool
     */
    public function deleteSprint(Sprint $sprint): bool
    {
        return $sprint->delete();
    }

    /**
     * Get sprint statistics.
     *
     * @param Sprint $sprint
     * @return array
     */
    public function getSprintStatistics(Sprint $sprint): array
    {
        // Load task counts by status_id
        $tasksByStatus = $sprint->tasks()
            ->select('status_id', DB::raw('count(*) as count'))
            ->groupBy('status_id')
            ->pluck('count', 'status_id')
            ->toArray();

        // Load task counts by responsible_id (assignee)
        $tasksByAssignee = $sprint->tasks()
            ->select('responsible_id', DB::raw('count(*) as count'))
            ->groupBy('responsible_id')
            ->pluck('count', 'responsible_id')
            ->toArray();

        // Calculate velocity if you have story_points column, otherwise return 0
        $velocity = 0;

        // Calculate completion percentage based on completed tasks vs total tasks
        $totalTasks = $sprint->tasks()->count();
        $completedTasks = $sprint->tasks()->whereHas('status', function($query) {
            $query->where('category', 'done');
        })->count();
        
        $completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        
        return [
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_assignee' => $tasksByAssignee,
            'completion_percentage' => $completionPercentage,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'velocity' => $velocity,
            'days_remaining' => 0, // Placeholder, calculate based on sprint dates if needed
            'is_active' => $sprint->status === SprintStatusEnum::ACTIVE->value,
            'is_completed' => $sprint->status === SprintStatusEnum::COMPLETED->value,
            'is_overdue' => $sprint->end_date && $sprint->end_date < now() && $sprint->status !== SprintStatusEnum::COMPLETED->value,
        ];
    }
}
