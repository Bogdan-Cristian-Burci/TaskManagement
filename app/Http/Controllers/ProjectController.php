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
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ProjectController extends Controller
{
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->middleware('auth:api');
        $this->authorizeResource(Project::class, 'project', [
            'except' => ['index', 'store']
        ]);
        $this->projectService = $projectService;
    }

    /**
     * Display a listing of the projects.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $filters = [];
        $with = ['organisation', 'team'];

        // Build filters from request parameters
        if ($request->has('organisation_id')) {
            $filters['organisation_id'] = $request->input('organisation_id');
        } else if ($request->user()->organisation_id) {
            // Default to user's organisation if no filter is specified
            $filters['organisation_id'] = $request->user()->organisation_id;
        }

        if ($request->has('team_id')) {
            $filters['team_id'] = $request->input('team_id');
        }

        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        // Filter by user involvement
        if ($request->has('my_projects') && $request->boolean('my_projects')) {
            $filters['user_id'] = $request->user()->id;
        }

        // Search by name or key
        if ($request->has('search')) {
            $filters['search'] = $request->input('search');
        }

        // Add relationships to load
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['users', 'boards', 'tasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        $projects = $this->projectService->getProjects($filters, $with);

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project in storage.
     *
     * @param ProjectRequest $request
     * @return ProjectResource
     */
    public function store(ProjectRequest $request): ProjectResource
    {
        $this->authorize('create', Project::class);

        $project = $this->projectService->createProject(
            $request->validated(),
            $request->input('board_type_id')
        );

        return new ProjectResource($project->load(['team', 'boards']));
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
        $with = ['organisation', 'team'];

        // Add relationships to load
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['users', 'boards', 'tasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        $project->load($with);

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
        $project = $this->projectService->updateProject($project, $request->validated());
        return new ProjectResource($project->load(['organisation', 'team']));
    }

    /**
     * Remove the specified project from storage.
     *
     * @param Request $request
     * @param Project $project
     * @return Response|JsonResponse
     */
    public function destroy(Request $request, Project $project): Response|JsonResponse
    {
        // Check if we should cascade delete related entities
        $cascadeDelete = $request->boolean('cascade_delete', false);

        try {
            $this->projectService->deleteProject($project, $cascadeDelete);
            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Restore a soft-deleted project.
     *
     * @param int $id
     * @return ProjectResource
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
     */
    public function attachUsers(AttachUserToProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('manageUsers', $project);

        $validated = $request->validated();
        $userIds = $validated['user_ids'];
        $role = $validated['role'] ?? 'member';

        try {
            $this->projectService->addUsersToProject($project, $userIds, $role);
            return new ProjectResource($project->fresh(['users']));
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Detach user from the project.
     *
     * @param DetachUserFromProjectRequest $request
     * @param Project $project
     * @return JsonResponse
     */
    public function detachUser(DetachUserFromProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageUsers', $project);

        $validated = $request->validated();

        try {
            $userId = $validated['user_id'];
            $reassignTasks = $request->boolean('reassign_tasks', false);
            $reassignToUserId = $request->input('reassign_to_user_id');

            $this->projectService->removeUserFromProject(
                $project,
                User::findOrFail($userId),
                $reassignTasks,
                $reassignToUserId
            );

            return response()->json([
                'message' => 'User successfully removed from project',
                'project' => new ProjectResource($project->fresh(['users']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_CONFLICT);
        }
    }

    /**
     * Update user role in the project.
     *
     * @param Request $request
     * @param Project $project
     * @param User $user
     * @return JsonResponse
     */
    public function updateUserRole(Request $request, Project $project, User $user): JsonResponse
    {
        $this->authorize('manageUsers', $project);

        $request->validate([
            'role' => 'required|string|in:manager,developer,member',
        ]);

        try {
            $this->projectService->updateUserRole($project, $user, $request->input('role'));

            return response()->json([
                'message' => 'User role updated successfully',
                'user_id' => $user->id,
                'role' => $request->input('role')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_BAD_REQUEST);
        }
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

        // Include active sprint if requested
        if ($request->has('with_active_sprint')) {
            $query->with('activeSprint');
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
        $filters = [];
        $with = ['status', 'assignee', 'board', 'boardColumn'];

        // Build filters from request
        if ($request->has('status_id')) {
            $filters['status_id'] = $request->input('status_id');
        }

        if ($request->has('priority_id')) {
            $filters['priority_id'] = $request->input('priority_id');
        }

        if ($request->has('assignee_id')) {
            $filters['assignee_id'] = $request->input('assignee_id');
        }

        // Add relationships to load
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['reporter', 'subtasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        // Add sort parameters
        $filters['sort'] = $request->input('sort', 'created_at');
        $filters['direction'] = $request->input('direction', 'desc');

        $tasks = $this->projectService->getProjectTasks($project, $filters, $with);
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
        $stats = $this->projectService->getProjectStatistics($project);
        return response()->json($stats);
    }

    /**
     * Add a board to a project.
     *
     * @param Request $request
     * @param Project $project
     * @return BoardResource
     */
    public function addBoard(Request $request, Project $project): BoardResource
    {
        $this->authorize('update', $project);

        $request->validate([
            'board_type_id' => 'required|exists:board_types,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
        ]);

        $attributes = array_filter($request->only(['name', 'description']));

        $board = $this->projectService->addBoard(
            $project,
            $request->input('board_type_id'),
            $attributes
        );

        return new BoardResource($board->load('columns'));
    }
}
