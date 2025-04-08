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
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TagController extends Controller
{

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
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
        $projectId = $request->input('project_id');

        $this->authorize('create', [Tag::class, $projectId]);

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
        $this->authorize('delete', $tag);

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
        
        // Use a more efficient query approach instead of union
        $query = Tag::where(function($q) use ($project) {
            // Project-specific tags
            $q->where('project_id', $project->id)
            // Or system tags for this organization  
            ->orWhere(function($q) use ($project) {
                $q->where('is_system', true)
                  ->where('organisation_id', $project->organisation_id)
                  ->whereNull('project_id');
            });
        });
        
        // Filter by name if provided
        if ($request->has('name')) {
            $searchTerm = "%" . $request->input('name') . "%";
            $query->where('name', 'LIKE', $searchTerm);
        }
        
        // Handle sorting with a single approach
        $sortColumn = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $validColumns = ['name', 'color', 'created_at'];
        
        $query->orderBy(in_array($sortColumn, $validColumns) ? $sortColumn : 'name', $sortDirection);
        
        // Eager load task counts when requested to avoid N+1 issues
        if ($request->boolean('with_counts', false)) {
            $query->withCount('tasks');
        }
        
        // Get the tags with a single efficient query
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

    /**
     * Import tags from another project to the current project.
     *
     * @param Request $request
     * @param Project $targetProject
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function importTags(Request $request, Project $targetProject): AnonymousResourceCollection
    {
        $this->authorize('update', $targetProject);

        $request->validate([
            'source_project_id' => 'required|exists:projects,id',
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id'
        ]);

        $sourceProject = Project::findOrFail($request->source_project_id);
        $this->authorize('view', $sourceProject);

        $importedTags = [];
        $tags = Tag::whereIn('id', $request->tag_ids)
            ->where('project_id', $sourceProject->id)
            ->get();

        foreach ($tags as $tag) {
            // Check if a tag with the same name already exists in the target project
            $existingTag = Tag::where('name', $tag->name)
                ->where('project_id', $targetProject->id)
                ->first();

            if (!$existingTag) {
                $newTag = Tag::create([
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'project_id' => $targetProject->id,
                    'organization_id' => $tag->organization_id, // Preserve organization link if present
                ]);

                $importedTags[] = $newTag;
            }
        }

        return TagResource::collection(collect($importedTags));
    }
}
