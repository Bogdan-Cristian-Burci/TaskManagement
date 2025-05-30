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

    protected TaskService $taskService;

    public function __construct(BoardService $boardService, TeamService $teamService, TaskService $taskService)
    {
        $this->boardService = $boardService;
        $this->teamService = $teamService;
        $this->taskService = $taskService;
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
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Collection
     */
    public function getProjects(array $filters = [], array $with = [])
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
        
        // Return paginated results if pagination is requested
        if (isset($filters['paginate']) && $filters['paginate']) {
            $perPage = $filters['per_page'] ?? 15; // Default 15 items per page
            return $query->paginate($perPage);
        }
        
        // Otherwise return all results as collection
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
                    $this->taskService->deleteProjectTasks($project);
                    break;

                case Project::TASK_HANDLING['MOVE']:
                    $targetProjectId = $options['target_project_id'] ?? null;

                    // Validate target project
                    if (!$targetProjectId) {
                        throw new \Exception('Target project ID is required for task movement');
                    }

                    $targetProject = Project::find($targetProjectId);
                    if (!$targetProject) {
                        throw new \Exception('Target project not found');
                    }

                    // Use TaskService to move tasks
                    $this->taskService->moveProjectTasks($project, $targetProject);
                    break;

                case Project::TASK_HANDLING['KEEP']:
                    // Keep tasks but detach them from project (orphaning tasks)
                    $this->taskService->detachProjectTasks($project);
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
                $this->taskService->reassignProjectTasks($project, $user, $reassignToUser);

            } else {
                // Unassign tasks using TaskService
                $this->taskService->reassignProjectTasks($project, $user, null);
            }

            // Remove user from project
            $project->users()->detach($user->id);

            \Log::info("User {$user->id} removed from project {$project->id}");

            return true;
        });
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
            ->select('statuses.name as status', DB::raw('count(*) as count'))
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

    /**
     * Get tasks for a specific project with optional filtering.
     *
     * @param Project $project
     * @param array $filters
     * @param array $with Related models to load
     * @return Collection
     */
    public function getProjectTasks(Project $project, array $filters = [], array $with = []): Collection
    {
        return $this->taskService->getProjectTasks($project, $filters, $with);
    }
}
