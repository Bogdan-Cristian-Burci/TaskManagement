<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Task::class, 'task');
    }
    public function index(Request $request)
    {
        $query = Task::query();

        // Apply filters
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('board_id')) {
            $query->where('board_id', $request->board_id);
        }

        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('responsible_id')) {
            $query->where('responsible_id', $request->responsible_id);
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        // Apply sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Load relationships for the resource
        $query->with([
            'project', 'board', 'boardColumn', 'status',
            'priority', 'taskType', 'responsible', 'reporter',
            'parentTask'
        ]);

        return TaskResource::collection(
            $query->paginate($request->get('per_page', 15))
        );
    }

    public function store(TaskRequest $request)
    {
        $task = Task::create($request->validated());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Task $task)
    {
        // Load relationships based on request
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

        $task->load($with);

        return new TaskResource($task);
    }

    public function update(TaskRequest $request, Task $task)
    {
        // Save old task data for history
        $oldData = $task->toArray();

        $task->update($request->validated());

        // Create history record
        $task->history()->create([
            'user_id' => auth()->id(),
            'old_data' => $oldData,
            'new_data' => $task->toArray(),
        ]);

        return new TaskResource($task);
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->noContent();
    }

    public function changeStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $request->validate([
            'status_id' => 'required|exists:statuses,id'
        ]);

        $oldStatus = $task->status_id;
        $task->update(['status_id' => $request->status_id]);

        // Log the status change in history
        $task->history()->create([
            'user_id' => auth()->id(),
            'field_changed' => 'status_id',
            'old_value' => $oldStatus,
            'new_value' => $request->status_id,
        ]);

        return new TaskResource($task);
    }

    public function assignTask(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $request->validate([
            'responsible_id' => 'required|exists:users,id'
        ]);

        $oldResponsible = $task->responsible_id;
        $task->update(['responsible_id' => $request->responsible_id]);

        // Log the assignment change in history
        $task->history()->create([
            'user_id' => auth()->id(),
            'field_changed' => 'responsible_id',
            'old_value' => $oldResponsible,
            'new_value' => $request->responsible_id,
        ]);

        return new TaskResource($task);
    }
}
