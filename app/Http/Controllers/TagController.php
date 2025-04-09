<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Http\Resources\TagResource;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
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
     * Get all available tags that a user can import across organizations.
     * This returns tags from all projects in all organizations the user has access to.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableTagsForImport(Request $request)
    {
        // Get the user's organizations with proper permissions
        $user = $request->user();
        $organisations = $user->getAccessibleOrganisations('tag.view');

        if ($organisations->isEmpty()) {
            return response()->json([
                'message' => 'No organizations found with tag view permissions.',
                'tags' => []
            ]);
        }

        // Get project ID if filtering by project
        $excludeProjectId = $request->input('exclude_project_id');

        // Get the organization IDs
        $organizationIds = $organisations->pluck('id')->toArray();

        // Query to get all tags from these organizations
        $tagsQuery = Tag::query()
            ->whereIn('organisation_id', $organizationIds)
            ->where(function (Builder $query) {
                // Include both project-specific tags and system tags
                $query->whereNotNull('project_id')
                    ->orWhere('is_system', true);
            });

        // Exclude tags from a specific project if requested
        if ($excludeProjectId) {
            $tagsQuery->where(function (Builder $query) use ($excludeProjectId) {
                $query->where('project_id', '!=', $excludeProjectId)
                    ->orWhereNull('project_id'); // Still include system tags
            });
        }

        // Apply filters if provided
        if ($request->has('name')) {
            $tagsQuery->where('name', 'LIKE', '%' . $request->input('name') . '%');
        }

        if ($request->has('color')) {
            $color = ltrim($request->input('color'), '#');
            $tagsQuery->where('color', 'LIKE', '%' . $color . '%');
        }

        // Include relationships
        $tagsQuery->with(['project:id,name,organisation_id', 'organisation:id,name']);

        // Apply sorting
        $sortColumn = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $validColumns = ['name', 'color', 'created_at'];

        if (in_array($sortColumn, $validColumns)) {
            $tagsQuery->orderBy($sortColumn, $sortDirection);
        } else {
            $tagsQuery->orderBy('name', 'asc');
        }

        // Group by organization and project for better frontend display
        $tags = $tagsQuery->get();

        // Transform tags into a grouped structure
        $groupedTags = $this->groupTagsByOrganizationAndProject($tags);

        return response()->json([
            'organizations' => $groupedTags
        ]);
    }

    /**
     * Import multiple tags from any organization/project to a target project.
     *
     * @param Request $request
     * @param $projectId
     * @return JsonResponse
     * @throws \Throwable
     */
    public function batchImportTags(Request $request, $projectId)
    {

        // Validate the project ID
        if (!is_numeric($projectId)) {
            return response()->json([
                'message' => 'Invalid project ID format'
            ], 400);
        }

        // Find the project
        $targetProject = Project::find($projectId);

        if (!$targetProject) {
            return response()->json([
                'message' => 'Project not found'
            ], 404);
        }

        $this->authorize('update', $targetProject);

        $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id'
        ]);

        $tagIds = $request->input('tag_ids');

        // Get all requested tags
        $tagsToImport = Tag::whereIn('id', $tagIds)->get();

        // Verify user has access to all source projects
        $sourceProjectIds = $tagsToImport->pluck('project_id')->filter()->unique();
        $sourceProjects = Project::whereIn('id', $sourceProjectIds)->get();

        foreach ($sourceProjects as $project) {
            if (!$request->user()->can('view', $project)) {
                return response()->json([
                    'message' => 'You do not have permission to access one or more source projects.',
                ], 403);
            }
        }

        // Check for any system tags from organizations other than the target
        $systemTags = $tagsToImport->where('is_system', true)->where('organisation_id', '!=', $targetProject->organisation_id);
        if ($systemTags->isNotEmpty()) {
            return response()->json([
                'message' => 'System tags can only be imported from the same organization.',
                'invalid_tag_ids' => $systemTags->pluck('id')
            ], 422);
        }

        $importedTags = [];
        $skippedTags = [];

        DB::beginTransaction();

        try {

            // Load the project with its organization
            $targetProject->load('organisation');

            // Verify we have a valid project ID
            if (!$targetProject->exists || !$targetProject->id) {
                throw new \Exception("Invalid target project. Project ID: " . ($targetProject->id ?? 'null'));
            }

            // Get organization context as fallback
            $organisationId = \App\Services\OrganizationContext::getCurrentOrganizationId();

            foreach ($tagsToImport as $sourceTag) {
                // For system tags in the same organization, just return them as already "imported"
                if ($sourceTag->is_system && $sourceTag->organisation_id === $targetProject->organisation_id) {
                    $importedTags[] = $sourceTag;
                    continue;
                }

                // Check if a tag with the same name already exists in the target project
                $existingTag = Tag::where('name', $sourceTag->name)
                    ->where('project_id', $targetProject->id)
                    ->first();

                if ($existingTag) {
                    $skippedTags[] = [
                        'id' => $sourceTag->id,
                        'name' => $sourceTag->name,
                        'reason' => 'Tag with the same name already exists in the target project',
                        'existing_tag' => $existingTag
                    ];
                    continue;
                }

                // If project has a valid organisation_id, use it
                if ($targetProject->organisation_id) {
                    $newTag = Tag::create([
                        'name' => $sourceTag->name,
                        'color' => $sourceTag->color,
                        'project_id' => $targetProject->id,
                        'organisation_id' => $targetProject->organisation_id,
                        'is_system' => false, // Imported tags are never system tags
                    ]);
                }
                // If target project has no organization_id, try to get from global context
                else if ($organisationId) {
                    $newTag = Tag::create([
                        'name' => $sourceTag->name,
                        'color' => $sourceTag->color,
                        'project_id' => $targetProject->id,
                        'organisation_id' => $organisationId,
                        'is_system' => false, // Imported tags are never system tags
                    ]);
                }
                // Last resort - try to get from the user's current organization
                else {
                    // Get the user's current organization
                    $userOrgId = $request->user()->organisation_id;

                    if (!$userOrgId) {
                        throw new \Exception("Cannot determine organisation_id for tag creation. Please select an organization first.");
                    }

                    $newTag = Tag::create([
                        'name' => $sourceTag->name,
                        'color' => $sourceTag->color,
                        'project_id' => $targetProject->id,
                        'organisation_id' => $userOrgId,
                        'is_system' => false, // Imported tags are never system tags
                    ]);
                }

                $importedTags[] = $newTag;
            }

            DB::commit();

            // Double check project ID for the response
            return response()->json([
                'message' => 'Tags imported successfully',
                'imported' => TagResource::collection(collect($importedTags)),
                'skipped' => $skippedTags,
                'target_project' => [
                    'id' => $targetProject->id,
                    'name' => $targetProject->name,
                    'organisation_id' => $targetProject->organisation_id
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to import tags: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to group tags by organization and project for better display.
     *
     * @param Collection $tags
     * @return array
     */
    protected function groupTagsByOrganizationAndProject(Collection $tags): array
    {
        $groupedTags = [];

        foreach ($tags as $tag) {
            $orgId = $tag->organisation_id;
            $orgName = $tag->organisation->name;
            $projectId = $tag->project_id;
            $projectName = $tag->project ? $tag->project->name : 'System Tags';

            // Initialize organization if not exists
            if (!isset($groupedTags[$orgId])) {
                $groupedTags[$orgId] = [
                    'id' => $orgId,
                    'name' => $orgName,
                    'projects' => []
                ];
            }

            // Initialize project if not exists
            $projectKey = $projectId ?: 'system';
            if (!isset($groupedTags[$orgId]['projects'][$projectKey])) {
                $groupedTags[$orgId]['projects'][$projectKey] = [
                    'id' => $projectId,
                    'name' => $projectName,
                    'is_system' => ($projectId === null),
                    'tags' => []
                ];
            }

            // Add the tag to the project
            $groupedTags[$orgId]['projects'][$projectKey]['tags'][] = new TagResource($tag);
        }

        // Convert to indexed arrays for JSON
        $result = [];
        foreach ($groupedTags as $org) {
            $projectsArray = [];
            foreach ($org['projects'] as $project) {
                $projectsArray[] = $project;
            }
            $org['projects'] = $projectsArray;
            $result[] = $org;
        }

        return $result;
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
                    'organisation_id' => $targetProject->organisation_id, // Use target project's organisation_id
                ]);

                $importedTags[] = $newTag;
            }
        }

        return TagResource::collection(collect($importedTags));
    }
}
