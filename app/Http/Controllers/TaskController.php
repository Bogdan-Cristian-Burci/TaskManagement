<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    protected TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
        $this->authorizeResource(Task::class, 'task');
    }

    /**
     * Display a listing of tasks with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [];
        $with = ['project', 'board', 'boardColumn', 'status',
            'priority', 'taskType', 'responsible', 'reporter'];

        // Build filters from request parameters
        if ($request->has('project_id')) {
            $filters['project_id'] = $request->project_id;
        }

        if ($request->has('board_id')) {
            $filters['board_id'] = $request->board_id;
        }

        if ($request->has('status_id')) {
            $filters['status_id'] = $request->status_id;
        }

        if ($request->has('responsible_id')) {
            $filters['responsible_id'] = $request->responsible_id;
        }

        if ($request->boolean('overdue')) {
            $filters['overdue'] = true;
        }

        // Apply sorting
        $filters['sort_by'] = $request->get('sort_by', 'created_at');
        $filters['sort_direction'] = $request->get('sort_direction', 'desc');

        $tasks = $this->taskService->getTasks($filters, $with);

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created task.
     *
     * @param TaskRequest $request
     * @return JsonResponse
     */
    public function store(TaskRequest $request): JsonResponse
    {
        // Add validation to ensure the column belongs to the board
        if ($request->filled('board_id') && $request->filled('board_column_id')) {
            $columnBelongsToBoard = BoardColumn::where('id', $request->board_column_id)
                ->where('board_id', $request->board_id)
                ->exists();

            if (!$columnBelongsToBoard) {
                return response()->json([
                    'message' => 'The selected board column does not belong to the specified board'
                ], 422);
            }
        }

        $task = $this->taskService->createTask($request->validated());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified task.
     *
     * @param Request $request
     * @param Task $task
     * @return TaskResource
     */
    public function show(Request $request, Task $task): TaskResource
    {
        // Build relationships to load
        $with = ['project', 'board', 'boardColumn', 'status', 'priority',
            'taskType', 'responsible', 'reporter'];

        if ($request->boolean('with_comments')) {
            $with[] = 'comments';
        }

        if ($request->boolean('with_attachments')) {
            $with[] = 'attachments';
        }

        if ($request->boolean('with_history')) {
            $with[] = 'history';
        }

        if ($request->boolean('with_subtasks')) {
            $with[] = 'subtasks';
        }

        if ($request->boolean('with_parent')) {
            $with[] = 'parentTask';
        }

        // Get task with relationships
        $task = $this->taskService->getTask($task->id, $with);

        return new TaskResource($task);
    }

    /**
     * Update the specified task.
     *
     * @param TaskRequest $request
     * @param Task $task
     * @return TaskResource
     */
    public function update(TaskRequest $request, Task $task): TaskResource
    {
        \Log::debug('received attributes in controller: '. json_encode($request->validated()));
        $task = $this->taskService->updateTask($task, $request->validated());
        return new TaskResource($task);
    }

    /**
     * Remove the specified task.
     *
     * @param Task $task
     * @return Response
     */
    public function destroy(Task $task): Response
    {
        $this->taskService->deleteTask($task);
        return response()->noContent();
    }

    /**
     * Move a task to a different column.
     *
     * @param Request $request
     * @param Task $task
     * @return JsonResponse
     */
    public function move(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'column_id' => 'required|exists:board_columns,id',
            'force' => 'sometimes|boolean',
        ]);

        $targetColumn = BoardColumn::findOrFail($request->input('column_id'));
        $force = $request->boolean('force', false);

        if ($force) {
            $this->authorize('forceMove', $task);
        }

        try {
            if ($task->board_id !== $targetColumn->board_id) {
                return response()->json([
                    'message' => 'Cannot move task to a column on a different board'
                ], 422);
            }

            $success = $this->taskService->moveTask($task, $targetColumn, $force);

            if (!$success) {
                return response()->json([
                    'message' => 'Task could not be moved to the target column',
                    'reasons' => [
                        'wip_limit_reached' => $targetColumn->isAtWipLimit(),
                        'not_allowed_transition' => !empty($task->boardColumn->allowed_transitions) &&
                            !in_array($targetColumn->id, $task->boardColumn->allowed_transitions ?? [])
                    ]
                ], 422);
            }

            return response()->json([
                'message' => 'Task moved successfully',
                'task' => new TaskResource($task->fresh(['boardColumn', 'status'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change the status of a task.
     *
     * @param Request $request
     * @param Task $task
     * @return TaskResource
     */
    public function changeStatus(Request $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $request->validate([
            'status_id' => 'required|exists:statuses,id'
        ]);

        $task = $this->taskService->changeTaskStatus($task, $request->status_id);
        return new TaskResource($task);
    }

    /**
     * Assign a task to a user.
     *
     * @param Request $request
     * @param Task $task
     * @return TaskResource|JsonResponse
     */
    public function assignTask(Request $request, Task $task): TaskResource|JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'responsible_id' => 'nullable|exists:users,id'
        ]);

        try {
            $task = $this->taskService->assignTask($task, $request->responsible_id);
            return new TaskResource($task);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get tasks by search criteria.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $filters = [];
        $with = ['status', 'priority', 'responsible', 'project', 'board'];

        // Process search terms
        if ($request->has('query')) {
            $filters['search'] = $request->input('query');
        }

        // Add other filters
        foreach (['project_id', 'board_id', 'status_id', 'priority_id', 'responsible_id'] as $field) {
            if ($request->has($field)) {
                $filters[$field] = $request->input($field);
            }
        }

        // Add special filters
        if ($request->boolean('my_tasks', false)) {
            $filters['responsible_id'] = auth()->id();
        }

        if ($request->boolean('overdue', false)) {
            $filters['overdue'] = true;
        }

        if ($request->boolean('due_soon', false)) {
            $filters['due_soon'] = true;
        }

        // Sorting
        $filters['sort_by'] = $request->input('sort_by', 'created_at');
        $filters['sort_direction'] = $request->input('sort_direction', 'desc');

        $tasks = $this->taskService->searchTasks($filters, $with);

        return TaskResource::collection($tasks);
    }
}
