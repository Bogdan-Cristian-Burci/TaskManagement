<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Http\Resources\TagResource;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TagController extends Controller
{

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->authorizeResource(Tag::class, 'tag', [
            'except' => ['index', 'store']
        ]);
    }

    /**
     * Display a listing of the tags.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::query();

        // Filter by project if provided
        if ($request->has('project_id')) {
            $projectId = $request->input('project_id');

            // Verify user has access to this project
            $project = Project::findOrFail($projectId);
            $this->authorize('view', $project);

            $query->where('project_id', $projectId);
        }

        // Filter by name if provided
        if ($request->has('name')) {
            $query->where('name', 'LIKE', "%{$request->input('name')}%");
        }

        // Filter by color if provided
        if ($request->has('color')) {
            $color = ltrim($request->input('color'), '#');
            $query->where('color', 'LIKE', "%{$color}%");
        }

        // Include task count
        if ($request->boolean('with_counts', false)) {
            $query->withCount('tasks');
        }

        // Include relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['project', 'tasks'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $query->with($include);
                }
            }
        }

        // Handle sorting
        $sortColumn = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $validColumns = ['name', 'color', 'created_at'];

        if (in_array($sortColumn, $validColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        $tags = $query->paginate($request->input('per_page', 15));

        return TagResource::collection($tags);
    }

    /**
     * Store a newly created tag in storage.
     *
     * @param TagRequest $request
     * @return TagResource
     * @throws AuthorizationException
     */
    public function store(TagRequest $request): TagResource
    {
        $this->authorize('create', [Tag::class, $request->input('project_id')]);

        $tag = Tag::create($request->validated());

        return new TagResource($tag->load('project'));
    }

    /**
     * Display the specified tag.
     *
     * @param Request $request
     * @param Tag $tag
     * @return TagResource
     */
    public function show(Request $request, Tag $tag): TagResource
    {
        // Load relationships if requested
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['project', 'tasks'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $tag->load($include);
                }
            }
        }

        // Load task count if requested
        if ($request->boolean('with_counts', false)) {
            $tag->loadCount('tasks');
        }

        return new TagResource($tag);
    }

    /**
     * Update the specified tag in storage.
     *
     * @param TagRequest $request
     * @param Tag $tag
     * @return TagResource
     */
    public function update(TagRequest $request, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return new TagResource($tag->load('project'));
    }

    /**
     * Remove the specified tag from storage.
     *
     * @param Tag $tag
     * @return Response
     */
    public function destroy(Tag $tag): Response
    {
        // Check if tag is used by tasks
        if ($tag->tasks()->count() > 0) {
            return response([
                'message' => 'This tag is currently used by one or more tasks and cannot be deleted.',
                'tasks_count' => $tag->tasks()->count()
            ], HttpResponse::HTTP_CONFLICT);
        }

        $tag->delete();

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted tag.
     *
     * @param int $id
     * @return TagResource
     * @throws AuthorizationException
     */
    public function restore(int $id): TagResource
    {
        $tag = Tag::withTrashed()->findOrFail($id);
        $this->authorize('restore', $tag);

        $tag->restore();

        return new TagResource($tag->load('project'));
    }

    /**
     * Get all tags for a specific project.
     *
     * @param Request $request
     * @param Project $project
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function forProject(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $query = $project->tags();

        // Filter by name if provided
        if ($request->has('name')) {
            $query->where('name', 'LIKE', "%{$request->input('name')}%");
        }

        // Include task count
        if ($request->boolean('with_counts', false)) {
            $query->withCount('tasks');
        }

        // Handle sorting
        $sortColumn = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $validColumns = ['name', 'color', 'created_at'];

        if (in_array($sortColumn, $validColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        $tags = $query->get();

        return TagResource::collection($tags);
    }

    /**
     * Batch create multiple tags for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function batchCreate(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('update', $project);

        $request->validate([
            'tags' => 'required|array|min:1',
            'tags.*.name' => 'required|string|max:50',
            'tags.*.color' => [
                'required',
                'string',
                'regex:/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/',
            ],
        ]);

        $tags = [];

        foreach ($request->tags as $tagData) {
            // Ensure color has # prefix
            if (!str_starts_with($tagData['color'], '#')) {
                $tagData['color'] = "#{$tagData['color']}";
            }

            // Ensure project_id is set
            $tagData['project_id'] = $project->id;

            // Create tag
            $tags[] = Tag::create($tagData);
        }

        return TagResource::collection(collect($tags));
    }
}
