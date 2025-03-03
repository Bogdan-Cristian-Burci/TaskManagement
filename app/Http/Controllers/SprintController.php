<?php

namespace App\Http\Controllers;

use App\Http\Requests\SprintRequest;
use App\Http\Requests\SprintTaskRequest;
use App\Http\Resources\SprintResource;
use App\Http\Resources\TaskResource;
use App\Models\Board;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

class SprintController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->authorizeResource(Sprint::class, 'sprint', [
            'except' => ['index', 'boardSprints', 'store']
        ]);
    }

    /**
     * Display a listing of all sprints.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sprint::class);

        $query = Sprint::query();

        // Filter by board if provided
        if ($request->has('board_id')) {
            $boardId = $request->input('board_id');

            // Verify user has access to this board
            $board = Board::findOrFail($boardId);
            $this->authorize('view', $board);

            $query->where('board_id', $boardId);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter active sprints
        if ($request->boolean('active', false)) {
            $query->active();
        }

        // Filter overdue sprints
        if ($request->boolean('overdue', false)) {
            $query->overdue();
        }

        // Filter by date range
        if ($request->has('start_after')) {
            $query->where('start_date', '>=', $request->input('start_after'));
        }

        if ($request->has('end_before')) {
            $query->where('end_date', '<=', $request->input('end_before'));
        }

        // Include relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['board', 'tasks'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $query->with($include);
                }
            }
        }

        // Include counts
        if ($request->boolean('with_counts', false)) {
            $query->withCount('tasks');
            $query->withCount(['tasks as completed_tasks_count' => function (Builder $q) {
                $q->where('status', 'completed');
            }]);
        }

        // Sort by field
        $sortField = $request->input('sort', 'start_date');
        $sortDirection = $request->input('direction', 'desc');
        $validSortFields = ['name', 'start_date', 'end_date', 'status', 'created_at'];

        if (in_array($sortField, $validSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('start_date', 'desc');
        }

        $sprints = $query->paginate($request->input('per_page', 15));

        return SprintResource::collection($sprints);
    }

    /**
     * Get sprints for a specific board.
     *
     * @param Request $request
     * @param Board $board
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function boardSprints(Request $request, Board $board): AnonymousResourceCollection
    {
        $this->authorize('view', $board);

        $query = $board->sprints();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Include relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['tasks'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $query->with($include);
                }
            }
        }

        // Include counts
        if ($request->boolean('with_counts', false)) {
            $query->withCount('tasks');
            $query->withCount(['tasks as completed_tasks_count' => function (Builder $q) {
                $q->where('status', 'completed');
            }]);
        }

        // Sort by field
        $sortDirection = $request->input('direction', 'desc');
        $sortField = $request->input('sort', 'start_date');

        $sprints = $query->orderBy($sortField, $sortDirection)->get();

        return SprintResource::collection($sprints);
    }

    /**
     * Store a newly created sprint in storage.
     *
     * @param SprintRequest $request
     * @return SprintResource
     * @throws AuthorizationException
     */
    public function store(SprintRequest $request): SprintResource
    {
        $this->authorize('create', [Sprint::class, $request->input('board_id')]);

        $sprint = Sprint::create($request->validated());

        return new SprintResource($sprint->load('board'));
    }

    /**
     * Display the specified sprint.
     *
     * @param Request $request
     * @param Sprint $sprint
     * @return SprintResource
     */
    public function show(Request $request, Sprint $sprint): SprintResource
    {
        // Load relationships if requested
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['board', 'tasks'];

            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $sprint->load($include);
                }
            }
        }

        // Load task counts if requested
        if ($request->boolean('with_counts', false)) {
            $sprint->loadCount('tasks');
            $sprint->loadCount(['tasks as completed_tasks_count' => function ($query) {
                $query->where('status', 'completed');
            }]);
        }

        return new SprintResource($sprint);
    }

    /**
     * Update the specified sprint in storage.
     *
     * @param SprintRequest $request
     * @param Sprint $sprint
     * @return SprintResource
     */
    public function update(SprintRequest $request, Sprint $sprint): SprintResource
    {
        $sprint->update($request->validated());

        return new SprintResource($sprint->load('board'));
    }

    /**
     * Remove the specified sprint from storage.
     *
     * @param Sprint $sprint
     * @return Response
     */
    public function destroy(Sprint $sprint): Response
    {
        // Check if sprint has tasks
        if ($sprint->tasks()->count() > 0) {
            return response([
                'message' => 'Cannot delete sprint that has associated tasks. Please remove tasks first.',
                'tasks_count' => $sprint->tasks()->count()
            ], ResponseAlias::HTTP_CONFLICT);
        }

        $sprint->delete();

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted sprint.
     *
     * @param int $id
     * @return SprintResource
     * @throws AuthorizationException
     */
    public function restore(int $id): SprintResource
    {
        $sprint = Sprint::withTrashed()->findOrFail($id);
        $this->authorize('restore', $sprint);

        $sprint->restore();

        return new SprintResource($sprint->load('board'));
    }

    /**
     * Start a sprint.
     *
     * @param Sprint $sprint
     * @return SprintResource | JsonResponse
     * @throws AuthorizationException
     */
    public function start(Sprint $sprint): SprintResource | JsonResponse
    {
        $this->authorize('start', $sprint);

        if ($sprint->status !== 'planning') {
            return response([
                'message' => 'Sprint can only be started from planning status.',
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY)->json();
        }

        $sprint->status = 'active';
        $sprint->save();

        return new SprintResource($sprint->load('board'));
    }

    /**
     * Complete a sprint.
     *
     * @param Request $request
     * @param Sprint $sprint
     * @return SprintResource | JsonResponse
     * @throws AuthorizationException|Throwable
     */
    public function complete(Request $request, Sprint $sprint): SprintResource | JsonResponse
    {
        $this->authorize('complete', $sprint);

        if ($sprint->status !== 'active') {
            return response([
                'message' => 'Only active sprints can be completed.',
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY)->json();
        }

        // If requested, move incomplete tasks to a new or existing sprint
        if ($request->has('move_incomplete_tasks')) {
            $targetSprintId = $request->input('target_sprint_id');

            DB::transaction(function() use ($sprint, $targetSprintId) {
                $incompleteTasks = $sprint->tasks()
                    ->where('status', '!=', 'completed')
                    ->get();

                // Detach tasks from current sprint
                $taskIds = $incompleteTasks->pluck('id')->toArray();
                $sprint->tasks()->detach($taskIds);

                // Attach to target sprint
                if ($targetSprintId) {
                    $targetSprint = Sprint::findOrFail($targetSprintId);
                    $targetSprint->tasks()->attach($taskIds);
                }
            });
        }

        $sprint->status = 'completed';
        $sprint->save();

        return new SprintResource($sprint->load('board'));
    }

    /**
     * Get tasks for a sprint.
     *
     * @param Request $request
     * @param Sprint $sprint
     * @return AnonymousResourceCollection
     */
    public function tasks(Request $request, Sprint $sprint): AnonymousResourceCollection
    {
        $query = $sprint->tasks();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by assignee
        if ($request->has('assignee_id')) {
            $query->where('assignee_id', $request->input('assignee_id'));
        }

        // Include relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['assignee', 'reporter', 'subtasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $query->with($include);
                }
            }
        }

        // Sort by field
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $validSortFields = ['title', 'status', 'priority', 'created_at', 'updated_at'];

        if (in_array($sortField, $validSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $tasks = $query->paginate($request->input('per_page', 15));

        return TaskResource::collection($tasks);
    }

    /**
     * Add tasks to a sprint.
     *
     * @param SprintTaskRequest $request
     * @param Sprint $sprint
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function addTasks(SprintTaskRequest $request, Sprint $sprint): JsonResponse
    {
        $this->authorize('manageTasks', $sprint);

        $taskIds = $request->input('task_ids');

        // Check that all tasks belong to the same project as the sprint's board
        $boardId = $sprint->board_id;
        $board = Board::findOrFail($boardId);
        $projectId = $board->project_id;

        $invalidTasks = Task::whereIn('id', $taskIds)
            ->where('project_id', '!=', $projectId)
            ->count();

        if ($invalidTasks > 0) {
            return response()->json([
                'message' => 'All tasks must belong to the same project as the sprint.',
                'invalid_tasks_count' => $invalidTasks
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Ensure tasks aren't already in another active sprint
        $activeSprints = Sprint::where('status', 'active')
            ->where('id', '!=', $sprint->id)
            ->whereHas('tasks', function ($query) use ($taskIds) {
                $query->whereIn('tasks.id', $taskIds);
            })
            ->count();

        if ($activeSprints > 0) {
            return response()->json([
                'message' => 'Some tasks are already assigned to other active sprints.',
                'active_sprints_count' => $activeSprints
            ], ResponseAlias::HTTP_CONFLICT);
        }

        // Attach tasks to sprint
        $sprint->tasks()->syncWithoutDetaching($taskIds);

        return response()->json([
            'message' => count($taskIds) . ' tasks added to sprint',
            'sprint_id' => $sprint->id,
            'task_ids' => $taskIds
        ]);
    }

    /**
     * Remove tasks from a sprint.
     *
     * @param SprintTaskRequest $request
     * @param Sprint $sprint
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function removeTasks(SprintTaskRequest $request, Sprint $sprint): JsonResponse
    {
        $this->authorize('manageTasks', $sprint);

        $taskIds = $request->input('task_ids');

        // Detach tasks from sprint
        $sprint->tasks()->detach($taskIds);

        return response()->json([
            'message' => count($taskIds) . ' tasks removed from sprint',
            'sprint_id' => $sprint->id,
            'task_ids' => $taskIds
        ]);
    }

    /**
     * Get sprint statistics.
     *
     * @param Sprint $sprint
     * @return JsonResponse
     */
    public function statistics(Sprint $sprint): JsonResponse
    {
        // Load task counts by status
        $tasksByStatus = $sprint->tasks()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Load task counts by assignee
        $tasksByAssignee = $sprint->tasks()
            ->select('assignee_id', DB::raw('count(*) as count'))
            ->groupBy('assignee_id')
            ->pluck('count', 'assignee_id')
            ->toArray();

        // Get completion percentage
        $totalTasks = $sprint->tasks()->count();
        $completedTasks = $sprint->tasks()->where('status', 'completed')->count();
        $completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        // Calculate velocity (story points completed)
        $velocity = $sprint->tasks()
            ->where('status', 'completed')
            ->sum('story_points');

        // Calculate daily completion trend
        $completedTasksByDay = $sprint->tasks()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return response()->json([
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_assignee' => $tasksByAssignee,
            'completion_percentage' => $completionPercentage,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'velocity' => $velocity,
            'completion_trend' => $completedTasksByDay,
            'days_remaining' => $sprint->days_remaining,
            'progress' => $sprint->progress,
            'is_active' => $sprint->is_active,
            'is_completed' => $sprint->is_completed,
            'is_overdue' => $sprint->is_overdue,
        ]);
    }
}
