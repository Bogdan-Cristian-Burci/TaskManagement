<?php

namespace App\Http\Controllers;

use App\Enums\SprintStatusEnum;
use App\Http\Requests\SprintRequest;
use App\Http\Requests\SprintTaskRequest;
use App\Http\Resources\SprintResource;
use App\Http\Resources\TaskResource;
use App\Models\Board;
use App\Models\Sprint;
use App\Models\Task;
use App\Services\SprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SprintController extends Controller
{
    protected SprintService $sprintService;

    public function __construct(SprintService $sprintService)
    {
        $this->middleware('auth:api');
        $this->authorizeResource(Sprint::class, 'sprint', [
            'except' => ['index', 'boardSprints', 'store']
        ]);
        $this->sprintService = $sprintService;
    }

    /**
     * Display a listing of all sprints. - not available since a sprint belongs to a project, that belongs to an organisation
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sprint::class);

        $filters = [];
        $with = [];

        // Build filters from request
        if ($request->has('board_id')) {
            $board = Board::findOrFail($request->input('board_id'));
            $this->authorize('view', $board);
            $filters['board_id'] = $board->id;
        }

        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        if ($request->boolean('active', false)) {
            $filters['active'] = true;
        }

        if ($request->boolean('overdue', false)) {
            $filters['overdue'] = true;
        }

        // Build relationships to include
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['board', 'tasks'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        // Build sort parameters
        $filters['sort'] = $request->input('sort', 'start_date');
        $filters['direction'] = $request->input('direction', 'desc');

        // If board ID was provided, use board sprints method
        if (isset($filters['board_id'])) {
            $board = Board::findOrFail($filters['board_id']);
            $sprints = $this->sprintService->getBoardSprints($board, $filters, $with);
        } else {
            // Otherwise query all sprints the user has access to
            // This would need a more complex implementation in SprintService
            $sprints = Sprint::with($with)->get();
        }

        return SprintResource::collection($sprints);
    }

    /**
     * Get sprints for a specific board.
     *
     * @param Request $request
     * @param Board $board
     * @return AnonymousResourceCollection
     */
    public function boardSprints(Request $request, Board $board): AnonymousResourceCollection
    {
        $filters = [];
        $with = [];

        // Build filters from request
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        // Build relationships to include
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['tasks'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        // Build sort parameters
        $filters['sort'] = $request->input('sort', 'start_date');
        $filters['direction'] = $request->input('direction', 'desc');

        $sprints = $this->sprintService->getBoardSprints($board, $filters, $with);

        return SprintResource::collection($sprints);
    }

    /**
     * Store a newly created sprint in storage.
     *
     * @param SprintRequest $request
     * @return SprintResource
     */
    public function store(SprintRequest $request): SprintResource
    {
        $this->authorize('create', [Sprint::class, $request->input('board_id')]);

        $board = Board::findOrFail($request->input('board_id'));
        $sprint = $this->sprintService->createSprint($board, $request->validated());

        return new SprintResource($sprint->load('board'));
    }

    /**
     * Store a sprint for a specific board.
     *
     * @param SprintRequest $request
     * @param Board $board
     * @return SprintResource
     */
    public function storeForBoard(SprintRequest $request, Board $board): SprintResource
    {
        $this->authorize('create', [Sprint::class, $board->id]);

        // Remove board_id from request data since we're using route model binding
        $data = $request->validated();
        unset($data['board_id']);

        $sprint = $this->sprintService->createSprint($board, $data);

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
        $with = [];

        // Load relationships if requested
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['board', 'tasks'];

            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }

            $sprint->load($with);
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
        $sprint = $this->sprintService->updateSprint($sprint, $request->validated());
        return new SprintResource($sprint->load('board'));
    }

    /**
     * Remove the specified sprint from storage.
     *
     * @param Sprint $sprint
     * @return Response|JsonResponse
     */
    public function destroy(Sprint $sprint): Response|JsonResponse
    {
        $canDelete = $this->sprintService->canDeleteSprint($sprint);

        if (!$canDelete['can_delete']) {
            return response()->json([
                'message' => 'Cannot delete sprint that has associated tasks. Please remove tasks first.',
                'tasks_count' => $canDelete['task_count'] ?? 0
            ], ResponseAlias::HTTP_CONFLICT);
        }

        $this->sprintService->deleteSprint($sprint);
        return response()->noContent();
    }

    /**
     * Restore a soft-deleted sprint.
     *
     * @param int $id
     * @return SprintResource
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
     * @return SprintResource|JsonResponse
     */
    public function start(Sprint $sprint): SprintResource|JsonResponse
    {
        $this->authorize('start', $sprint);

        try {
            $sprint = $this->sprintService->startSprint($sprint);
            return new SprintResource($sprint->load('board'));
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Complete a sprint.
     *
     * @param Request $request
     * @param Sprint $sprint
     * @return SprintResource|JsonResponse
     */
    public function complete(Request $request, Sprint $sprint): SprintResource|JsonResponse
    {
        $this->authorize('complete', $sprint);

        try {
            $moveIncompleteTo = null;

            // If requested, move incomplete tasks to a new or existing sprint
            if ($request->has('move_incomplete_tasks') && $request->has('target_sprint_id')) {
                $moveIncompleteTo = Sprint::findOrFail($request->input('target_sprint_id'));

                // Check if target sprint is in the same board
                if ($moveIncompleteTo->board_id !== $sprint->board_id) {
                    return response()->json([
                        'message' => 'Target sprint must be in the same board'
                    ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $sprint = $this->sprintService->completeSprint($sprint, $moveIncompleteTo);
            return new SprintResource($sprint->load('board'));
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
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
        $filters = [];
        $with = ['status', 'assignee', 'boardColumn'];

        // Add filters from request
        if ($request->has('status_id')) {
            $filters['status_id'] = $request->input('status_id');
        }

        if ($request->has('assignee_id')) {
            $filters['assignee_id'] = $request->input('assignee_id');
        }

        // Add sort parameters
        $filters['sort'] = $request->input('sort', 'created_at');
        $filters['direction'] = $request->input('direction', 'desc');

        // Add additional relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['reporter', 'subtasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        $tasks = $this->sprintService->getSprintTasks($sprint, $filters, $with);

        return TaskResource::collection($tasks);
    }

    /**
     * Add tasks to a sprint.
     *
     * @param SprintTaskRequest $request
     * @param Sprint $sprint
     * @return JsonResponse
     */
    public function addTasks(SprintTaskRequest $request, Sprint $sprint): JsonResponse
    {
        $this->authorize('manageTasks', $sprint);

        try {
            $taskIds = $request->input('task_ids');
            $tasks = $this->sprintService->addTasksToSprint($sprint, $taskIds);

            return response()->json([
                'message' => count($taskIds) . ' tasks added to sprint',
                'sprint_id' => $sprint->id,
                'task_ids' => $taskIds,
                'tasks' => TaskResource::collection($tasks)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Remove tasks from a sprint.
     *
     * @param SprintTaskRequest $request
     * @param Sprint $sprint
     * @return JsonResponse
     */
    public function removeTasks(SprintTaskRequest $request, Sprint $sprint): JsonResponse
    {
        $this->authorize('manageTasks', $sprint);

        $taskIds = $request->input('task_ids');
        $this->sprintService->removeTasksFromSprint($sprint, $taskIds);

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
        $stats = $this->sprintService->getSprintStatistics($sprint);
        return response()->json($stats);
    }
}
