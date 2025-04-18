<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Http\Requests\TaskTagRequest;
use App\Http\Resources\TaskResource;
use App\Models\BoardColumn;
use App\Models\StatusTransition;
use App\Models\Tag;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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

        // Build the relationships to load based on the requested filters
        // Only load relationships that are needed to improve performance
        $with = ['status']; // Always include status for permission checks

        if ($request->has('project_id')) {
            $filters['project_id'] = $request->project_id;
            $with[] = 'project';
        }

        if ($request->has('board_id')) {
            $filters['board_id'] = $request->board_id;
            $with[] = 'board';
            $with[] = 'boardColumn';
        }

        if ($request->has('responsible_id')) {
            $filters['responsible_id'] = $request->responsible_id;
            $with[] = 'responsible';
        }

        // Add other common relations that are frequently needed
        if ($request->boolean('with_all_relations', false)) {
            $with = array_merge($with, ['priority', 'taskType', 'reporter']);
        }

        if ($request->boolean('overdue')) {
            $filters['overdue'] = true;
        }

        // Apply sorting
        $filters['sort_by'] = $request->get('sort_by', 'created_at');
        $filters['sort_direction'] = $request->get('sort_direction', 'desc');

        // Get tasks with optimized relations
        $tasks = $this->taskService->getTasks($filters, array_unique($with));

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
                // Check if WIP limit is the issue
                $isWipLimitReached = $targetColumn->isAtWipLimit();

                // Check if transition is not allowed
                $isNotAllowedTransition = false;
                if ($task->boardColumn && $task->boardColumn->maps_to_status_id && $targetColumn->maps_to_status_id) {
                    // Check transition validity using StatusTransition
                    $isNotAllowedTransition = !StatusTransition::where('from_status_id', $task->boardColumn->maps_to_status_id)
                        ->where('to_status_id', $targetColumn->maps_to_status_id)
                        ->where(function($query) use ($task) {
                            $query->where('board_id', $task->board_id)
                                ->orWhereNull('board_id'); // Include global transitions
                        })
                        ->exists();
                }

                return response()->json([
                    'message' => 'Task could not be moved to the target column',
                    'reasons' => [
                        'wip_limit_reached' => $isWipLimitReached,
                        'not_allowed_transition' => $isNotAllowedTransition
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

    /**
     * Assign tags to a task.
     *
     * @param TaskTagRequest $request
     * @param Task $task
     * @return TaskResource|JsonResponse
     */
    public function assignTags(TaskTagRequest $request, Task $task)
    {
        $tagIds = $request->input('tag_ids');

        try {
            DB::beginTransaction();

            // Remove existing tags
            if ($request->input('replace', true)) {
                $task->tags()->detach();
            }

            // Add new tags
            if (!empty($tagIds)) {
                $task->tags()->attach($tagIds);
            }

            DB::commit();

            // Load the task with its new tags
            $task->load('tags');

            return new TaskResource($task);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to assign tags: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a tag from a task.
     *
     * @param Task $task
     * @param Tag $tag
     * @return TaskResource|JsonResponse
     */
    public function removeTag(Task $task, Tag $tag)
    {
        try {
            // Check if the tag is currently assigned to the task
            if (!$task->tags->contains($tag->id)) {
                return response()->json([
                    'message' => 'Tag is not assigned to this task.',
                ], 400);
            }

            $task->tags()->detach($tag->id);

            // Reload the task with updated tags
            $task->load('tags');

            return new TaskResource($task);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove tag: ' . $e->getMessage(),
            ], 500);
        }
    }
}
