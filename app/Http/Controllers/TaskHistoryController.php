<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskHistoryRequest;
use App\Http\Resources\TaskHistoryResource;
use App\Models\TaskHistory;

class TaskHistoryController extends Controller
{


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
