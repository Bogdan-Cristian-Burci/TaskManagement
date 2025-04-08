<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        return DB::transaction(function() use ($attributes) {
        // If no board_column_id is specified but a board_id is, assign to the first column
        if (!isset($attributes['board_column_id']) && isset($attributes['board_id'])) {
            $firstColumn = BoardColumn::where('board_id', $attributes['board_id'])
                ->orderBy('position')
                ->first();

            if ($firstColumn) {
                $attributes['board_column_id'] = $firstColumn->id;
            }
        }

            // Generate task number if not provided
            if (!isset($attributes['task_number'])) {
                $attributes['task_number'] = $this->generateTaskNumber($attributes['project_id']);
            }

            $task = Task::create($attributes);

            // Create initial history record
            $task->history()->create([
                'user_id' => auth()->id() ?? 0,
                'field_changed' => 'created',
                'change_type_id' => 1,
                'new_value' => 'Task created'
            ]);

            return $task;
        });
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

    /**
     * Search for tasks based on criteria.
     *
     * @param array $filters
     * @param array $with Related models to load
     * @return Collection
     */
    public function searchTasks(array $filters, array $with = []): Collection
    {
        $query = Task::query();

        // Ensure organization context if not using global scope
        $orgId = OrganizationContext::getCurrentOrganizationId();
        if ($orgId) {
            $query->whereHas('project', function($q) use ($orgId) {
                $q->where('organisation_id', $orgId);
            });
        }

        // Text search
        if (isset($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Apply standard filters
        foreach (['project_id', 'board_id', 'status_id', 'priority_id', 'responsible_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Special filters
        if (isset($filters['overdue']) && $filters['overdue']) {
            $query->overdue();
        }

        if (isset($filters['due_soon']) && $filters['due_soon']) {
            $query->where('due_date', '>=', now())
                ->where('due_date', '<=', now()->addDays(3));
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
     * Delete all tasks associated with a project.
     * Used during project deletion process.
     *
     * @param Project $project
     * @return int Number of tasks deleted
     */
    public function deleteProjectTasks(Project $project): int
    {
        $taskCount = $project->tasks()->count();

        if ($taskCount === 0) {
            Log::info("No tasks to delete for project {$project->id} ({$project->name})");
            return 0;
        }

        return DB::transaction(function() use ($project, $taskCount) {
            // Get all task IDs in a single query
            $taskIds = $project->tasks()->pluck('id');
            
            if ($taskIds->isNotEmpty()) {
                // Bulk delete all related data in a single query for each type
                DB::table('comments')->whereIn('task_id', $taskIds)->delete();
                DB::table('attachments')->whereIn('task_id', $taskIds)->delete();
                DB::table('task_histories')->whereIn('task_id', $taskIds)->delete();
                
                // Detach from sprints and tags in bulk
                DB::table('sprint_task')->whereIn('task_id', $taskIds)->delete();
                DB::table('task_tag')->whereIn('task_id', $taskIds)->delete();
            }
            
            // Delete the tasks with a single query
            $project->tasks()->delete();

            Log::info("Deleted {$taskCount} tasks from project {$project->id} ({$project->name})");

            return $taskCount;
        });
    }

    /**
     * Move tasks from one project to another.
     * Used during project deletion process.
     *
     * @param Project $sourceProject
     * @param Project $targetProject
     * @return int Number of tasks moved
     * @throws \Exception When validation fails
     */
    public function moveProjectTasks(Project $sourceProject, Project $targetProject): int
    {
        // Cannot move to the same project
        if ($sourceProject->id === $targetProject->id) {
            throw new \Exception('Cannot move tasks to the same project');
        }

        // Ensure target project is in the same organization
        if ($sourceProject->organisation_id !== $targetProject->organisation_id) {
            throw new \Exception('Cannot move tasks to a project in a different organization');
        }

        $taskCount = $sourceProject->tasks()->count();

        if ($taskCount === 0) {
            Log::info("No tasks to move from project {$sourceProject->id} ({$sourceProject->name})");
            return 0;
        }

        // Get target default board and column if exists
        $targetBoard = $targetProject->boards()->first();
        $targetBoardId = $targetBoard?->id;
        $targetColumnId = $targetBoard?->columns()->first()?->id;

        return DB::transaction(function() use ($sourceProject, $targetProject, $targetBoardId, $targetColumnId, $taskCount) {
            // Bulk update all tasks at once for better performance
            $sourceProject->tasks()->update([
                'project_id' => $targetProject->id,
                'board_id' => $targetBoardId,
                'board_column_id' => $targetColumnId,
            ]);
            
            // Get all moved tasks for history records
            $taskIds = $sourceProject->tasks()->pluck('id')->toArray();
            
            if (!empty($taskIds)) {
                // Create history records in bulk
                $historyRecords = [];
                $now = now();
                $userId = auth()->id() ?? 0;
                
                foreach ($taskIds as $taskId) {
                    $historyRecords[] = [
                        'task_id' => $taskId,
                        'action' => 'moved',
                        'user_id' => $userId,
                        'details' => json_encode([
                            'from_project_id' => $sourceProject->id,
                            'to_project_id' => $targetProject->id
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                // Bulk insert all history records
                if (!empty($historyRecords)) {
                    DB::table('task_histories')->insert($historyRecords);
                }
            }

            Log::info("Moved {$taskCount} tasks from project {$sourceProject->id} to project {$targetProject->id}");

            return $taskCount;
        });
    }

    /**
     * Detach tasks from a project without deleting them.
     * This potentially creates orphaned tasks, use with caution.
     * Used during project deletion process.
     *
     * @param Project $project
     * @return int Number of tasks detached
     * @throws \Exception|\Throwable If database constraints prevent detachment
     */
    public function detachProjectTasks(Project $project): int
    {
        $taskCount = $project->tasks()->count();

        if ($taskCount === 0) {
            Log::info("No tasks to detach for project {$project->id} ({$project->name})");
            return 0;
        }

        try {
            // Some databases might prevent nullifying project_id if there's a NOT NULL constraint
            DB::transaction(function() use ($project) {
                // Update tasks to remove project association
                $project->tasks()->update([
                    'project_id' => null,
                    'board_id' => null,
                    'board_column_id' => null
                ]);
            });

            Log::info("Detached {$taskCount} tasks from project {$project->id} ({$project->name})");

            return $taskCount;
        } catch (\Exception $e) {
            Log::error("Failed to detach tasks from project {$project->id}: " . $e->getMessage());
            throw new \Exception('Cannot detach tasks from project due to database constraints. Consider using a different task handling option.');
        }
    }

    /**
     * Bulk reassign tasks from one user to another within a project.
     *
     * @param Project $project
     * @param User $fromUser
     * @param User|null $toUser Set to null to unassign tasks
     * @return int Number of tasks reassigned
     */
    public function reassignProjectTasks(Project $project, User $fromUser, ?User $toUser = null): int
    {
        $query = $project->tasks()->where('responsible_id', $fromUser->id);
        $taskCount = $query->count();

        if ($taskCount === 0) {
            return 0;
        }

        $newResponsibleId = $toUser?->id;

        // If a new user is specified, verify they are part of the project
        if ($toUser && !$project->users()->where('users.id', $toUser->id)->exists()) {
            throw new \Exception('Cannot reassign tasks to a user who is not a project member.');
        }

        DB::transaction(function() use ($query, $newResponsibleId, $fromUser, $toUser, $project) {
            $query->update(['responsible_id' => $newResponsibleId]);

            $action = $toUser ? "reassigned to user {$toUser->id}" : "unassigned";
            Log::info("Tasks {$action} from user {$fromUser->id} in project {$project->id}");
        });

        return $taskCount;
    }

    /**
     * Generate a unique task number for a project
     *
     * @param int $projectId
     * @return string
     */
    public function generateTaskNumber(int $projectId): string
    {
        $project = Project::findOrFail($projectId);
        $prefix = $project->code ?? 'TASK';

        $lastNumber = Task::where('project_id', $projectId)
            ->orderByDesc('id')
            ->value('task_number');

        $counter = 1;
        if ($lastNumber) {
            $parts = explode('-', $lastNumber);
            $counter = (int)end($parts) + 1;
        }

        return $prefix . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Add task to sprint
     */
    public function addTaskToSprint(Task $task, int $sprintId): bool
    {
        $sprint = Sprint::findOrFail($sprintId);

        // Check if sprint is in same project as task
        if ($sprint->project_id !== $task->project_id) {
            throw new \Exception('Cannot add task to sprint in different project');
        }

        return (bool)$task->sprints()->syncWithoutDetaching([$sprintId]);
    }
}
