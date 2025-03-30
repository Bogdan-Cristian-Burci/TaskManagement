<?php

namespace App\Services;

use App\Models\Board;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectService
{
    protected BoardService $boardService;
    protected TeamService $teamService;

    public function __construct(BoardService $boardService, TeamService $teamService)
    {
        $this->boardService = $boardService;
        $this->teamService = $teamService;
    }


    /**
     * Create a project with optional team and board.
     *
     * @param array $projectData
     * @param int|null $boardTypeId
     * @param User|null $creator
     * @return Project
     * @throws \Throwable
     */
    public function createProject(array $projectData, ?int $boardTypeId = null, User $creator = null): Project
    {
        return DB::transaction(function() use ($projectData, $boardTypeId, $creator) {
            // Set the creator as the responsible user if not specified
            if ($creator && !isset($projectData['responsible_user_id'])) {
                $projectData['responsible_user_id'] = $creator->id;
            }

            // Create the project
            $project = Project::create($projectData);

            // Create a board if a board type ID is provided
            if ($boardTypeId) {
                $this->boardService->createBoard($project, $boardTypeId);
            }

            // Add creator as a project member if not already
            if ($creator) {
                $project->users()->syncWithoutDetaching([$creator->id]);
            }

            return $project;
        });
    }

    /**
     * Update an existing project.
     *
     * @param Project $project
     * @param array $attributes
     * @return Project
     */
    public function updateProject(Project $project, array $attributes): Project
    {
        $project->update($attributes);
        return $project->fresh();
    }

    /**
     * Add a new board to an existing project.
     *
     * @param Project $project
     * @param int $boardTypeId
     * @param array $attributes
     * @return Board
     */
    public function addBoard(Project $project, int $boardTypeId, array $attributes = []): Board
    {
        return $this->boardService->createBoard($project, $boardTypeId, $attributes);
    }

    /**
     * Get all projects with optional filtering.
     *
     * @param array $filters
     * @param array $with Related models to load
     * @return Collection
     */
    public function getProjects(array $filters = [], array $with = []): Collection
    {
        $query = Project::query();

        // Apply filters
        if (isset($filters['organisation_id'])) {
            $query->where('organisation_id', $filters['organisation_id']);
        }

        if (isset($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->whereHas('users', function($q) use ($filters) {
                $q->where('users.id', $filters['user_id']);
            });
        }

        // Search by name or key
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('key', 'like', "%{$filters['search']}%");
            });
        }

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get a specific project by ID.
     *
     * @param int $projectId
     * @param array $with Related models to load
     * @return Project|null
     */
    public function getProject(int $projectId, array $with = []): ?Project
    {
        return Project::with($with)->find($projectId);
    }

    /**
     * Delete a project with advanced task handling options.
     *
     * @param Project $project The project to delete
     * @param string $taskHandlingOption Task handling option from ProjectService::TASK_HANDLING
     * @param array $options Additional options including target_project_id for task movement
     * @return bool
     * @throws \Exception|\Throwable When validation fails or the operation cannot be completed
     */
    public function deleteProject(
        Project $project,
        string $taskHandlingOption = Project::TASK_HANDLING['DELETE'],
        array $options = []
    ): bool
    {
        // Validate the task handling option
        if (!in_array($taskHandlingOption, array_values(Project::TASK_HANDLING))) {
            throw new \Exception("Invalid task handling option: {$taskHandlingOption}");
        }

        return DB::transaction(function() use ($project, $taskHandlingOption, $options) {
            // Pre-count tasks and users for logging
            $taskCount = $project->tasks()->count();
            $userCount = $project->users()->count();

            // Log the deletion intent
            Log::info("Project deletion initiated for project {$project->id} ({$project->name}) with task handling option: {$taskHandlingOption}");

            switch ($taskHandlingOption) {
                case Project::TASK_HANDLING['DELETE']:
                    $this->handleTaskDeletion($project);
                    break;

                case Project::TASK_HANDLING['MOVE']:
                    $targetProjectId = $options['target_project_id'] ?? null;
                    $this->handleTaskMovement($project, $targetProjectId);
                    break;

                case Project::TASK_HANDLING['KEEP']:
                    // Keep tasks but detach them from project (orphaning tasks)
                    $this->handleTaskDetachment($project);
                    break;
            }

            // Clean up project associations
            $this->cleanupProjectAssociations($project);

            // Log the deletion
            activity()
                ->performedOn($project)
                ->causedBy(auth()->user())
                ->withProperties([
                    'name' => $project->name,
                    'key' => $project->key,
                    'task_count' => $taskCount,
                    'user_count' => $userCount,
                    'handling_option' => $taskHandlingOption
                ])
                ->log('project_deleted');

            // Delete the project
            return $project->delete();
        });
    }

    /**
     * Handle task deletion for a project
     *
     * @param Project $project
     * @return void
     */
    private function handleTaskDeletion(Project $project): void
    {
        $taskCount = $project->tasks()->count();

        // Delete all child entities (cascade using task service if available)
        foreach ($project->tasks as $task) {
            // Delete comments, attachments, and history for each task
            // Better if delegated to a TaskService, but keeping it here per your preference
            $task->comments()->delete();
            $task->attachments()->delete();
            $task->history()->delete();
        }

        // Delete the tasks
        $project->tasks()->delete();

        Log::info("Deleted {$taskCount} tasks from project {$project->id}");
    }

    /**
     * Handle task movement to another project
     *
     * @param Project $project
     * @param int|null $targetProjectId
     * @throws \Exception When target project validation fails
     * @return void
     */
    private function handleTaskMovement(Project $project, ?int $targetProjectId): void
    {
        // Validate target project
        if (!$targetProjectId) {
            throw new \Exception('Target project ID is required for task movement');
        }

        $targetProject = Project::find($targetProjectId);
        if (!$targetProject) {
            throw new \Exception('Target project not found');
        }

        // Cannot move to the same project
        if ($project->id === $targetProject->id) {
            throw new \Exception('Cannot move tasks to the same project');
        }

        // Ensure target project is in the same organization
        if ($project->organisation_id !== $targetProject->organisation_id) {
            throw new \Exception('Cannot move tasks to a project in a different organization');
        }

        // Get target default board and column if exists
        $targetBoard = $targetProject->boards()->first();
        $targetBoardId = $targetBoard?->id;
        $targetColumnId = $targetBoard?->columns()->first()?->id;

        // Process tasks in chunks to avoid memory issues
        $taskCount = 0;
        $project->tasks()->chunkById(100, function ($tasks) use ($targetProject, $targetBoardId, $targetColumnId, &$taskCount) {
            foreach ($tasks as $task) {
                $taskCount++;

                // Update task with new project and board information
                $taskUpdateData = [
                    'project_id' => $targetProject->id,
                    'board_id' => $targetBoardId,
                    'board_column_id' => $targetColumnId,
                ];

                // Update the task
                $task->update($taskUpdateData);

                // Record history if available
                if (method_exists($task, 'recordHistory')) {
                    $task->recordHistory('moved', [
                        'from_project_id' => $project->id,
                        'to_project_id' => $targetProject->id,
                        'by_user_id' => auth()->id() ?? 0
                    ]);
                }
            }
        });

        Log::info("Moved {$taskCount} tasks from project {$project->id} to project {$targetProject->id}");
    }

    /**
     * Handle task detachment (orphaning tasks)
     *
     * @param Project $project
     * @return void
     */
    private function handleTaskDetachment(Project $project): void
    {
        // This option might not be desirable in most cases due to data integrity
        // But keeping it as an option for specific use cases
        $taskCount = $project->tasks()->count();

        // Nullify the project_id on tasks
        // Warning: This may violate constraints if project_id is NOT NULL
        $project->tasks()->update(['project_id' => null]);

        Log::info("Detached {$taskCount} tasks from project {$project->id}");
    }

    /**
     * Clean up project associations before deletion
     *
     * @param Project $project
     * @return void
     */
    private function cleanupProjectAssociations(Project $project): void
    {
        // Detach users from project
        $project->users()->detach();

        // Delete project boards if they exist and aren't already handled
        foreach ($project->boards as $board) {
            // Use boardService if it's not handling cascading deletions
            $this->boardService->deleteBoard($board, true);
        }

        // Handle other project associations
        $project->tags()->delete(); // Delete project-specific tags
    }
    /**
     * Add users to a project with specified roles.
     *
     * @param Project $project
     * @param array $userIds
     * @return Collection Users attached to the project
     */
    public function addUsersToProject(Project $project, array $userIds): Collection
    {

        // Attach users with roles without detaching existing users
        $project->users()->syncWithoutDetaching($userIds);

        return $project->users()->whereIn('users.id', $userIds)->get();
    }

    /**
     * Remove a user from a project and optionally reassign their tasks.
     *
     * @param Project $project The project from which to remove the user
     * @param User $user The user to be removed from the project
     * @param bool $reassignTasks Whether to reassign the user's tasks
     * @param int|null $reassignToUserId The ID of the user to reassign tasks to (required if $reassignTasks is true)
     * @return bool True if the operation was successful
     * @throws \Exception|\Throwable If validation fails or the operation cannot be completed
     */
    public function removeUserFromProject(
        Project $project,
        User $user,
        bool $reassignTasks = false,
        ?int $reassignToUserId = null
    ): bool
    {
        return DB::transaction(function() use ($project, $user, $reassignTasks, $reassignToUserId) {
            // Check if the user is actually in the project
            if (!$project->users()->where('users.id', $user->id)->exists()) {
                throw new \Exception('User is not a member of this project.');
            }

            // Check if we're removing the responsible user
            if ($project->responsible_user_id === $user->id) {
                throw new \Exception('Cannot remove the responsible user. Change the responsible user first.');
            }

            // Handle task reassignment
            if ($reassignTasks) {
                // Validate reassignToUserId is provided
                if ($reassignToUserId === null) {
                    throw new \Exception('A user ID must be provided to reassign tasks.');
                }

                // Verify the reassign-to user exists and is a member of the project
                $reassignToUser = User::find($reassignToUserId);
                if (!$reassignToUser) {
                    throw new \Exception('The user to reassign tasks to does not exist.');
                }

                if (!$project->users()->where('users.id', $reassignToUserId)->exists()) {
                    throw new \Exception('Cannot reassign tasks to a user who is not a project member.');
                }

                // Perform the reassignment - using responsible_id from Task model
                $tasksReassigned = $project->tasks()
                    ->where('responsible_id', $user->id)
                    ->update(['responsible_id' => $reassignToUserId]);

                \Log::info("Reassigned {$tasksReassigned} tasks from user {$user->id} to user {$reassignToUserId} in project {$project->id}");


            } else {
                // Unassign tasks if not being reassigned
                $tasksUnassigned = $project->tasks()
                    ->where('responsible_id', $user->id)
                    ->update(['responsible_id' => null]);

                \Log::info("Unassigned {$tasksUnassigned} tasks from user {$user->id} in project {$project->id}");
            }

            // Remove user from project
            $project->users()->detach($user->id);

            \Log::info("User {$user->id} removed from project {$project->id}");

            return true;
        });
    }

    /**
     * Update user's role in a project.
     *
     * @param Project $project
     * @param User $user
     * @param string $role
     * @return bool
     */
    public function updateUserRole(Project $project, User $user, string $role): bool
    {
        // Check if user is part of the project
        if (!$project->users->contains($user->id)) {
            throw new \Exception('User is not a member of this project.');
        }

        // Update the role
        $project->users()->updateExistingPivot($user->id, ['role' => $role]);

        return true;
    }

    /**
     * Get project statistics.
     *
     * @param Project $project
     * @return array
     */
    public function getProjectStatistics(Project $project): array
    {
        // Load necessary relationships
        $project->load(['tasks', 'boards.sprints', 'users']);

        // Load task counts by status and priority
        $tasksByStatus = $project->tasks()
            ->select('status.name as status', DB::raw('count(*) as count'))
            ->join('statuses', 'tasks.status_id', '=', 'statuses.id')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $tasksByPriority = $project->tasks()
            ->select('priorities.name as priority', DB::raw('count(*) as count'))
            ->join('priorities', 'tasks.priority_id', '=', 'priorities.id')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Calculate completion percentage
        $totalTasks = $project->tasks()->count();
        $completedTasks = $project->tasks()
            ->whereHas('status', function($q) {
                $q->where('name', 'Completed');
            })
            ->count();

        $completionPercentage = $totalTasks > 0 ?
            round(($completedTasks / $totalTasks) * 100, 2) : 0;

        // Calculate project timeline
        $daysTotal = $project->start_date && $project->end_date ?
            $project->start_date->diffInDays($project->end_date) : null;
        $daysElapsed = $project->start_date ?
            $project->start_date->diffInDays(now()) : null;
        $daysRemaining = $project->end_date ?
            now()->diffInDays($project->end_date, false) : null;

        return [
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_priority' => $tasksByPriority,
            'completion_percentage' => $completionPercentage,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'open_tasks' => $totalTasks - $completedTasks,
            'users_count' => $project->users->count(),
            'boards_count' => $project->boards->count(),
            'active_sprints' => $project->boards->sum(function($board) {
                return $board->sprints()->where('status', 'active')->count();
            }),
            'timeline' => [
                'days_total' => $daysTotal,
                'days_elapsed' => $daysElapsed,
                'days_remaining' => $daysRemaining,
                'is_overdue' => $project->is_overdue,
            ]
        ];
    }
}
