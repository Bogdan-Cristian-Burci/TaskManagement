<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetachUserFromTeamRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Http\Requests\AttachUserToProjectRequest;
use App\Http\Requests\DetachUserFromProjectRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::with(['organisation', 'team'])
            ->withCount(['boards', 'tasks'])
            ->get();

        return ProjectResource::collection($projects);
    }

    public function store(ProjectRequest $request): ProjectResource
    {
        $project = Project::create($request->validated());

        // Generate key if not provided
        if (!$project->key) {
            $project->key = strtoupper(substr($project->name, 0, 3)) . '-' . $project->id;
            $project->save();
        }

        return new ProjectResource($project->load(['organisation', 'team']));
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource(
            $project->load(['organisation', 'team', 'users'])
                ->loadCount(['boards', 'tasks'])
        );
    }

    public function update(ProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        return new ProjectResource($project->load(['organisation', 'team']));
    }

    public function destroy(Project $project): Response
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }

    public function attachUsers(AttachUserToProjectRequest $request, Project $project): ProjectResource
    {
        $validated = $request->validated();

        // Attach users without specifying roles
        $project->users()->syncWithoutDetaching($validated['user_ids']);

        return new ProjectResource($project->load('users'));
    }

    public function detachUser(DetachUserFromProjectRequest $request, Project $project): ProjectResource
    {
        $validated = $request->validated();

        $project->users()->detach($validated['user_ids']);

        return new ProjectResource($project->load('users'));
    }

}
