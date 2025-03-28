<?php

namespace App\Services;

use App\Models\Board;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
     * Delete a project and optionally its related entities.
     *
     * @param Project $project
     * @param bool $cascadeDelete Whether to delete related boards, tasks, etc.
     * @return bool
     */
    public function deleteProject(Project $project, bool $cascadeDelete = false): bool
    {
        return DB::transaction(function() use ($project, $cascadeDelete) {
            if ($cascadeDelete) {
                // Delete all boards associated with this project
                foreach ($project->boards as $board) {
                    $this->boardService->deleteBoard($board, true);
                }

                // Delete all tasks associated with this project
                $project->tasks()->delete();
            } else {
                // Simply detach relationships to preserve data
                $project->users()->detach();
            }

            return $project->delete();
        });
    }

    /**
     * Add users to a project with specified roles.
     *
     * @param Project $project
     * @param array $userIds
     * @param string $role Default role for the users
     * @return Collection Users attached to the project
     */
    public function addUsersToProject(Project $project, array $userIds, string $role = 'member'): Collection
    {
        // Prepare data with pivot values
        $usersWithRoles = [];
        foreach ($userIds as $userId) {
            $usersWithRoles[$userId] = ['role' => $role];
        }

        // Attach users with roles without detaching existing users
        $project->users()->syncWithoutDetaching($usersWithRoles);

        return $project->users()->whereIn('users.id', $userIds)->get();
    }

    /**
     * Remove a user from a project.
     *
     * @param Project $project
     * @param User $user
     * @param bool $reassignTasks Whether to reassign the user's tasks
     * @param int|null $reassignToUserId User ID to reassign tasks to, null to unassign
     * @return bool
     */
    public function removeUserFromProject(
        Project $project,
        User $user,
        bool $reassignTasks = false,
        ?int $reassignToUserId = null
    ): bool
    {
        return DB::transaction(function() use ($project, $user, $reassignTasks, $reassignToUserId) {
            // Check if we're removing the last project manager
            $isLastManager = $project->users()
                    ->wherePivot('role', 'manager')
                    ->count() === 1 &&
                $project->users()
                    ->where('users.id', $user->id)
                    ->wherePivot('role', 'manager')
                    ->exists();

            if ($isLastManager) {
                throw new \Exception('Cannot remove the last project manager.');
            }

            // Handle task reassignment
            if ($reassignTasks) {
                $project->tasks()
                    ->where('assignee_id', $user->id)
                    ->update(['assignee_id' => $reassignToUserId]);
            }

            // Remove user from project
            $project->users()->detach($user->id);

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
