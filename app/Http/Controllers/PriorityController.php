<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriorityRequest;
use App\Http\Resources\PriorityResource;
use App\Models\Priority;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PriorityController extends Controller
{
    use AuthorizesRequests;
    public function __construct()
    {
        $this->authorizeResource(Priority::class);
    }

    public function index(): AnonymousResourceCollection
    {
        return PriorityResource::collection(Priority::withCount('tasks')->get());
    }

    public function store(PriorityRequest $request): PriorityResource
    {
        $priority = Priority::create($request->validated());
        return new PriorityResource($priority);
    }

    public function show(Priority $priority): PriorityResource
    {
        return new PriorityResource($priority->loadCount('tasks'));
    }

    public function update(PriorityRequest $request, Priority $priority): PriorityResource
    {
        $priority->update($request->validated());
        return new PriorityResource($priority);
    }

    public function destroy(Priority $priority): Response
    {
        $priority->delete();
        return response()->noContent();
    }

    public function reorder(Request $request): Response
    {
        $this->authorize('update', Priority::class);

        $request->validate([
            'priorities' => ['required', 'array'],
            'priorities.*.id' => ['required', 'exists:priorities,id'],
            'priorities.*.position' => ['required', 'integer']
        ]);

        foreach ($request->priorities as $priorityData) {
            $priority = Priority::find($priorityData['id']);
            $priority->update(['position' => $priorityData['position']]);
        }

        return response()->noContent();
    }
}
