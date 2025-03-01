<?php

namespace App\Http\Controllers;

use App\Http\Requests\SprintRequest;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SprintController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return SprintResource::collection(Sprint::all());
    }

    public function store(SprintRequest $request)
    {
        return new SprintResource(Sprint::create($request->validated()));
    }

    public function show(Sprint $sprint)
    {
        return new SprintResource($sprint);
    }

    public function update(SprintRequest $request, Sprint $sprint)
    {
        $sprint->update($request->validated());

        return new SprintResource($sprint);
    }

    public function destroy(Sprint $sprint)
    {
        $sprint->delete();

        return response()->json();
    }
}
