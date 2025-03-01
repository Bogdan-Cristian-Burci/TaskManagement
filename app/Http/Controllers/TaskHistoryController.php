<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskHistoryRequest;
use App\Http\Resources\TaskHistoryResource;
use App\Models\TaskHistory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskHistoryController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return TaskHistoryResource::collection(TaskHistory::all());
    }

    public function store(TaskHistoryRequest $request)
    {
        return new TaskHistoryResource(TaskHistory::create($request->validated()));
    }

    public function show(TaskHistory $taskHistory)
    {
        return new TaskHistoryResource($taskHistory);
    }

    public function update(TaskHistoryRequest $request, TaskHistory $taskHistory)
    {
        $taskHistory->update($request->validated());

        return new TaskHistoryResource($taskHistory);
    }

    public function destroy(TaskHistory $taskHistory)
    {
        $taskHistory->delete();

        return response()->json();
    }
}
