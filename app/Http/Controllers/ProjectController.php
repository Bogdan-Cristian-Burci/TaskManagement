<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachUserToProjectRequest;
use App\Http\Requests\DetachUserFromProjectRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ProjectController extends Controller
{

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->authorizeResource(Project::class, 'project', [
            'except' => ['index', 'store']
        ]);
    }

    /**
     * Display a listing of the projects.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query();

        // Filter by organisation if specified
        if ($request->has('organisation_id')) {
            $query->where('organisation_id', $request->input('organisation_id'));
        } else if ($request->user()->organisation_id) {
            // Default to user's organisation if no filter is specified
            $query->where('organisation_id', $request->user()->organisation_id);
        }

        // Filter by team if specified
        if ($request->has('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }

        // Filter by status if specified
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by user involvement
        if ($request->has('my_projects') && $request->boolean('my_projects')) {
            $query->whereHas('users', function (Builder $q) use ($request) {
                $q->where('users.id', $request->user()->id);
            });
        }

        // Search by name or key
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Include relationships
        $relationships = ['organisation', 'team'];

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['users', 'boards', 'tasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $relationships[] = $include;
                }
            }
        }

        $query->with($relationships);

        // Add count metrics
        $counters = ['boards', 'tasks', 'users'];
        if ($request->has('with_counts')) {
            $query->withCount($counters);

            // Add open tasks count
            $query->withCount(['tasks as open_tasks_count' => function (Builder $q) {
                $q->where('status', '!=', 'completed');
            }]);
        }

        // Handle sorting
        $sortColumn = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $validColumns = ['name', 'key', 'created_at', 'updated_at', 'status', 'start_date', 'end_date'];

        if (in_array($sortColumn, $validColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $projects = $query->paginate($request->input('per_page', 15));

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project in storage.
     *
     * @param ProjectRequest $request
     * @return ProjectResource
     * @throws AuthorizationException|\Throwable
     */
    public function store(ProjectRequest $request): ProjectResource
    {
        $this->authorize('create', Project::class);

        $validatedData = $request->validated();

        $project = DB::transaction(function () use ($validatedData, $request) {
            // Create the project
            $project = Project::create($validatedData);

            // Generate key if not provided
            if (!$project->key) {
                $project->key = strtoupper(substr($project->name, 0, 3)) . '-' . $project->id;
                $project->save();
            }

            // Add the creator as a project manager
            $project->users()->attach($request->user()->id, ['role' => 'manager']);

            // Create a default board for the project
            if (!$request->has('skip_default_board') || !$request->boolean('skip_default_board')) {
                $project->boards()->create([
                    'name' => 'Default Board',
                    'description' => 'Default board for ' . $project->name,
                    'is_default' => true,
                ]);
            }

            return $project;
        });

        return new ProjectResource(
            $project->load(['organisation', 'team', 'users', 'boards'])
        );
    }

    /**
     * Display the specified project.
     *
     * @param Request $request
     * @param Project $project
     * @return ProjectResource
     */
    public function show(Request $request, Project $project): ProjectResource
    {
        $relationships = ['organisation', 'team'];

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['users', 'boards', 'tasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $relationships[] = $include;
                }
            }
        }

        $project->load($relationships);

        if ($request->has('with_counts')) {
            $project->loadCount(['boards', 'tasks', 'users']);

            // Add open tasks count
            $project->loadCount(['tasks as open_tasks_count' => function ($query) {
                $query->where('status', '!=', 'completed');
            }]);
        }

        return new ProjectResource($project);
    }

    /**
     * Update the specified project in storage.
     *
     * @param ProjectRequest $request
     * @param Project $project
     * @return ProjectResource
     */
    public function update(ProjectRequest $request, Project $project): ProjectResource
    {
        $validatedData = $request->validated();
        $project->update($validatedData);

        return new ProjectResource($project->load(['organisation', 'team']));
    }

    /**
     * Remove the specified project from storage.
     *
     * @param Project $project
     * @return Response
     */
    public function destroy(Project $project): Response
    {
        // Check if there are any tasks with dependencies in this project
        $hasTaskDependencies = $project->tasks()
            ->whereHas('dependencies')
            ->orWhereHas('dependents')
            ->exists();

        if ($hasTaskDependencies) {
            return response([
                'message' => 'Cannot delete project with task dependencies. Please remove dependencies first.',
                'has_task_dependencies' => true
            ], ResponseAlias::HTTP_CONFLICT);
        }

        $project->delete();

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted project.
     *
     * @param int $id
     * @return ProjectResource
     * @throws AuthorizationException
     */
    public function restore(int $id): ProjectResource
    {
        $project = Project::withTrashed()->findOrFail($id);
        $this->authorize('restore', $project);

        $project->restore();

        return new ProjectResource($project->load(['organisation', 'team']));
    }

    /**
     * Attach users to the project.
     *
     * @param AttachUserToProjectRequest $request
     * @param Project $project
     * @return ProjectResource
     * @throws AuthorizationException
     */
    public function attachUsers(AttachUserToProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('manageUsers', $project);

        $validated = $request->validated();
        $userIds = $validated['user_ids'];
        $role = $validated['role'] ?? 'member';

        // Prepare data with pivot values
        $usersWithRoles = [];
        foreach ($userIds as $userId) {
            $usersWithRoles[$userId] = ['role' => $role];
        }

        // Attach users with roles without detaching existing users
        $project->users()->syncWithoutDetaching($usersWithRoles);

        return new ProjectResource($project->load('users'));
    }

    /**
     * Detach user from the project.
     *
     * @param DetachUserFromProjectRequest $request
     * @param Project $project
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function detachUser(DetachUserFromProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageUsers', $project);

        $validated = $request->validated();

        // Check that we're not removing the last manager
        if ($project->users()->wherePivot('role', 'manager')->count() === 1) {
            $userToRemove = User::find($validated['user_id']);
            $userIsManager = $project->users()
                ->where('users.id', $validated['user_id'])
                ->wherePivot('role', 'manager')
                ->exists();

            if ($userIsManager) {
                return response()->json([
                    'message' => 'Cannot remove the last project manager. Assign a new manager first.'
                ], ResponseAlias::HTTP_CONFLICT);
            }
        }

        // Detach the user
        $project->users()->detach($validated['user_id']);

        // Also remove user from any tasks in this project
        $project->tasks()
            ->where('assignee_id', $validated['user_id'])
            ->update(['assignee_id' => null]);

        return (new ProjectResource($project->load('users')))->response();
    }

    /**
     * Update user role in the project.
     *
     * @param Request $request
     * @param Project $project
     * @param User $user
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function updateUserRole(Request $request, Project $project, User $user): JsonResponse
    {
        $this->authorize('manageUsers', $project);

        $request->validate([
            'role' => 'required|string|in:manager,developer,member',
        ]);

        // Check if user is part of the project
        if (!$project->users->contains($user->id)) {
            return response()->json([
                'message' => 'User is not a member of this project.'
            ], ResponseAlias::HTTP_BAD_REQUEST);
        }

        // Update the role
        $project->users()->updateExistingPivot($user->id, ['role' => $request->role]);

        return response()->json([
            'message' => 'User role updated successfully',
            'user_id' => $user->id,
            'role' => $request->role
        ]);
    }

    /**
     * Get users for a project.
     *
     * @param Project $project
     * @return AnonymousResourceCollection
     */
    public function users(Project $project): AnonymousResourceCollection
    {
        $users = $project->users()->with('roles')->get();

        return UserResource::collection($users);
    }

    /**
     * Get boards for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return AnonymousResourceCollection
     */
    public function boards(Request $request, Project $project): AnonymousResourceCollection
    {
        $query = $project->boards();

        // Include columns if requested
        if ($request->has('with_columns')) {
            $query->with('columns');
        }

        $boards = $query->get();

        return BoardResource::collection($boards);
    }

    /**
     * Get tasks for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return AnonymousResourceCollection
     */
    public function tasks(Request $request, Project $project): AnonymousResourceCollection
    {
        $query = $project->tasks();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // Filter by assignee
        if ($request->has('assignee_id')) {
            $query->where('assignee_id', $request->input('assignee_id'));
        }

        // Include relationships
        $relationships = [];

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['assignee', 'board', 'column', 'reporter', 'subtasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $relationships[] = $include;
                }
            }

            $query->with($relationships);
        }

        // Sort by specified field
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $validSortFields = ['created_at', 'updated_at', 'title', 'priority', 'status', 'due_date'];

        if (in_array($sortField, $validSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $tasks = $query->paginate($request->input('per_page', 15));

        return TaskResource::collection($tasks);
    }

    /**
     * Get project statistics.
     *
     * @param Project $project
     * @return JsonResponse
     */
    public function statistics(Project $project): JsonResponse
    {
        // Load task counts by status
        $tasksByStatus = $project->tasks()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Load task counts by priority
        $tasksByPriority = $project->tasks()
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Calculate completion percentage
        $totalTasks = $project->tasks()->count();
        $completedTasks = $project->tasks()->where('status', 'completed')->count();
        $completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        // Get recent activity
        $recentActivity = $project->tasks()
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->with('assignee')
            ->get()
            ->map(function($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'updated_at' => $task->updated_at,
                    'assignee' => $task->assignee ? [
                        'id' => $task->assignee->id,
                        'name' => $task->assignee->name
                    ] : null
                ];
            });

        return response()->json([
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_priority' => $tasksByPriority,
            'completion_percentage' => $completionPercentage,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'recent_activity' => $recentActivity,
            'users_count' => $project->users()->count(),
            'days_remaining' => $project->end_date ? now()->diffInDays($project->end_date, false) : null,
            'is_overdue' => $project->is_overdue,
        ]);
    }
}
