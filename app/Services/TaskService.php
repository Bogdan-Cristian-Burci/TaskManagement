<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Create a new task.
     *
     * @param array $attributes
     * @return Task
     */
    public function createTask(array $attributes): Task
    {
        // If no board_column_id is specified but a board_id is, assign to the first column
        if (!isset($attributes['board_column_id']) && isset($attributes['board_id'])) {
            $firstColumn = BoardColumn::where('board_id', $attributes['board_id'])
                ->orderBy('position')
                ->first();

            if ($firstColumn) {
                $attributes['board_column_id'] = $firstColumn->id;
            }
        }

        return Task::create($attributes);
    }

    /**
     * Update a task.
     *
     * @param Task $task
     * @param array $attributes
     * @return Task
     */
    public function updateTask(Task $task, array $attributes): Task
    {
        $task->update($attributes);
        return $task->fresh();
    }

    /**
     * Move a task to a different column.
     *
     * @param Task $task
     * @param BoardColumn $targetColumn
     * @param bool $force Whether to bypass workflow rules
     * @return bool
     */
    public function moveTask(Task $task, BoardColumn $targetColumn, bool $force = false): bool
    {
        // Check if columns belong to the same board
        if ($task->boardColumn && $task->boardColumn->board_id !== $targetColumn->board_id) {
            return false;
        }

        // Store old column before moving
        $oldColumn = $task->boardColumn;

        // Move the task
        $result = $task->moveToColumn($targetColumn, $force);

        return $result;
    }

    /**
     * Change the status of a task.
     *
     * @param Task $task
     * @param int $statusId
     * @return Task
     */
    public function changeTaskStatus(Task $task, int $statusId): Task
    {
        $task->update(['status_id' => $statusId]);

        return $task->fresh();
    }

    /**
     * Assign a task to a user.
     *
     * @param Task $task
     * @param int|null $userId User ID to assign, or null to unassign
     * @return Task
     * @throws \Exception If the user is not part of the project
     */
    public function assignTask(Task $task, ?int $userId): Task
    {
        // If unassigning, simply update and return
        if ($userId === null) {
            $task->update(['responsible_id' => null]);
            return $task->fresh();
        }

        // Check if user is part of the project
        $projectHasUser = $task->project->users()
            ->where('users.id', $userId)
            ->exists();

        if (!$projectHasUser) {
            throw new \Exception('User is not a member of the project.');
        }

        $task->update(['responsible_id' => $userId]);

        return $task->fresh();
    }

    /**
     * Get all tasks with optional filtering.
     *
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getTasks(array $filters, array $with = []): Collection
    {
        $query = Task::query();

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['board_id'])) {
            $query->where('board_id', $filters['board_id']);
        }

        if (isset($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        if (isset($filters['responsible_id'])) {
            $query->where('responsible_id', $filters['responsible_id']);
        }

        if (isset($filters['overdue']) && $filters['overdue']) {
            $query->overdue();
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get task by ID.
     *
     * @param int $taskId
     * @param array $with
     * @return Task|null
     */
    public function getTask(int $taskId, array $with = []): ?Task
    {
        return Task::with($with)->find($taskId);
    }

    /**
     * Delete a task.
     *
     * @param Task $task
     * @return bool
     */
    public function deleteTask(Task $task): bool
    {
        return DB::transaction(function() use ($task) {
            // Detach from sprints
            $task->sprints()->detach();

            // Delete the task
            return $task->delete();
        });
    }

    /**
     * Get tasks for a specific project.
     *
     * @param Project $project
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getProjectTasks(Project $project, array $filters = [], array $with = []): Collection
    {
        $query = $project->tasks();

        // Apply filters
        if (isset($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        if (isset($filters['priority_id'])) {
            $query->where('priority_id', $filters['priority_id']);
        }

        if (isset($filters['responsible_id'])) {
            $query->where('responsible_id', $filters['responsible_id']);
        }

        // Apply sorting
        $sortBy = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get tasks assigned to a specific user.
     *
     * @param User $user
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getUserTasks(User $user, array $filters = [], array $with = []): Collection
    {
        $query = Task::where('responsible_id', $user->id);

        // Apply filters
        if (isset($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['overdue']) && $filters['overdue']) {
            $query->overdue();
        }

        // Apply sorting
        $sortBy = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }
}
