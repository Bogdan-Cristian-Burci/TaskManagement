<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachUserToProjectRequest;
use App\Http\Requests\DetachUserFromProjectRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ProjectController extends Controller
{
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->middleware('auth:api')->except(['downloadDocument', 'getDocumentThumbnail', 'getDocumentPreview']);
        $this->authorizeResource(Project::class, 'project', [
            'except' => ['index', 'store', 'downloadDocument', 'getDocumentThumbnail', 'getDocumentPreview']
        ]);
        $this->projectService = $projectService;
    }

    /**
     * Display a listing of the projects with pagination.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $filters = [];
        $with = ['responsibleUser'];

        // Build filters from request parameters
        if ($request->has('organisation_id')) {
            $filters['organisation_id'] = $request->input('organisation_id');
        } else if ($request->user()->organisation_id) {
            // Default to user's organisation if no filter is specified
            $filters['organisation_id'] = $request->user()->organisation_id;
        }

        if ($request->has('team_id')) {
            $filters['team_id'] = $request->input('team_id');
        }

        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        // Filter by user involvement
        if ($request->has('my_projects') && $request->boolean('my_projects')) {
            $filters['user_id'] = $request->user()->id;
        }

        // Search by name or key
        if ($request->has('search')) {
            $filters['search'] = $request->input('search');
        }

        // Add relationships to load
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['users', 'boards', 'tasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        // Setup pagination
        $filters['paginate'] = true;
        if ($request->has('per_page')) {
            $filters['per_page'] = (int)$request->input('per_page');
        }

        $projects = $this->projectService->getProjects($filters, $with);

        // Add withCount for tasks
        $projects->each(function ($project) {
            $project->tasks_count = $project->tasks()->count();
        });

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project in storage.
     *
     * @param ProjectRequest $request
     * @return ProjectResource | JsonResponse
     */
    public function store(ProjectRequest $request): ProjectResource | JsonResponse
    {
        $this->authorize('create', Project::class);

        try {
            $validated = $request->validated();

            // Handle the simplified project creation flow
            $project = $this->projectService->createProject(
                $validated,
                $request->input('board_type_id'),
                $request->user() // Pass the creator user
            );

            return new ProjectResource($project->load(['team', 'boards', 'responsibleUser']));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create project: ' . $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Display the specified project.
     *
     * @param Request $request
     * @param Project $project
     * @return ProjectResource
     */
    public function show(Request $request, Project $project): ProjectResource
    {
        $with = ['organisation', 'team', 'boards.boardType.template','users'];

        // Add relationships to load
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['users', 'boards', 'tasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        $project->load($with);

        // Add tasks_count manually
        $project->tasks_count = $project->tasks()->count();

        return new ProjectResource($project);
    }

    /**
     * Update the specified project in storage.
     *
     * @param ProjectRequest $request
     * @param Project $project
     * @return ProjectResource
     */
    public function update(ProjectRequest $request, Project $project): ProjectResource
    {
        $project = $this->projectService->updateProject($project, $request->validated());
        $project->load(['organisation', 'team']);
        $project->tasks_count = $project->tasks()->count();
        return new ProjectResource($project);
    }

    /**
     * Remove the specified project from storage.
     *
     * @param Request $request
     * @param Project $project
     * @return Response|JsonResponse
     */
    public function destroy(Request $request, Project $project): Response|JsonResponse
    {
        try {
            // Validate request parameters
            $request->validate([
                'task_handling' => 'sometimes|string|in:' . implode(',', array_values(Project::TASK_HANDLING)),
                'target_project_id' => 'required_if:task_handling,' . Project::TASK_HANDLING['MOVE'] . '|exists:projects,id',
            ]);

            // For backward compatibility, map cascade_delete to appropriate task handling option
            if ($request->has('cascade_delete')) {
                $taskHandlingOption = $request->boolean('cascade_delete') ?
                    Project::TASK_HANDLING['DELETE'] :
                    Project::TASK_HANDLING['KEEP'];
            } else {
                // Get task handling option (default to DELETE)
                $taskHandlingOption = $request->input('task_handling', Project::TASK_HANDLING['DELETE']);
            }

            // Prepare options
            $options = [];
            if ($taskHandlingOption === Project::TASK_HANDLING['MOVE'] && $request->has('target_project_id')) {
                $options['target_project_id'] = $request->input('target_project_id');
            }

            // Delete project
            $this->projectService->deleteProject($project, $taskHandlingOption, $options);

            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted project.
     *
     * @param int $id
     * @return ProjectResource
     */
    public function restore(int $id): ProjectResource
    {
        $project = Project::withTrashed()->findOrFail($id);
        $this->authorize('restore', $project);

        $project->restore();
        $project->load(['organisation', 'team']);
        $project->tasks_count = $project->tasks()->count();
        return new ProjectResource($project);
    }

    /**
     * Attach users to the project.
     *
     * @param AttachUserToProjectRequest $request
     * @param Project $project
     * @return ProjectResource | JsonResponse
     */
    public function attachUsers(AttachUserToProjectRequest $request, Project $project): ProjectResource | JsonResponse
    {

        $validated = $request->validated();
        $userIds = $validated['user_ids'];

        try {
            $this->projectService->addUsersToProject($project, $userIds);
            $freshProject = $project->fresh(['users']);
            $freshProject->tasks_count = $freshProject->tasks()->count();
            return new ProjectResource($freshProject);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Detach a user from a project.
     *
     * @param Project $project
     * @param User $user
     * @param DetachUserFromProjectRequest $request
     * @return JsonResponse
     */
    public function detachUser(Project $project, User $user, DetachUserFromProjectRequest $request): JsonResponse
    {

        try {
            $reassignTasks = $request->boolean('reassign_tasks', false);
            $reassignToUserId = $request->input('reassign_to_user_id');

            $this->projectService->removeUserFromProject(
                $project,
                $user,
                $reassignTasks,
                $reassignToUserId
            );

            $freshProject = $project->fresh(['users']);
            $freshProject->tasks_count = $freshProject->tasks()->count();
            return response()->json([
                'message' => 'User successfully removed from project',
                'project' => new ProjectResource($freshProject)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ResponseAlias::HTTP_CONFLICT);
        }
    }

    /**
     * Get users for a project.
     *
     * @param Project $project
     * @return AnonymousResourceCollection
     */
    public function users(Project $project): AnonymousResourceCollection
    {
        $users = $project->users()->with('roles')->get();
        return UserResource::collection($users);
    }

    /**
     * Get boards for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return AnonymousResourceCollection
     */
    public function boards(Request $request, Project $project): AnonymousResourceCollection
    {
        $query = $project->boards();

        // Include columns if requested
        if ($request->has('with_columns')) {
            $query->with('columns');
        }

        // Include active sprint if requested
        if ($request->has('with_active_sprint')) {
            $query->with('activeSprint');
        }

        $boards = $query->get();
        return BoardResource::collection($boards);
    }

    /**
     * Get tasks for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return AnonymousResourceCollection
     */
    public function tasks(Request $request, Project $project): AnonymousResourceCollection
    {
        $filters = [];
        $with = ['status', 'responsible', 'board', 'boardColumn'];

        // Build filters from request
        if ($request->has('status_id')) {
            $filters['status_id'] = $request->input('status_id');
        }

        if ($request->has('priority_id')) {
            $filters['priority_id'] = $request->input('priority_id');
        }

        if ($request->has('responsible_id')) {
            $filters['responsible_id'] = $request->input('responsible_id');
        }

        // Add relationships to load
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['reporter', 'subtasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }

        // Add sort parameters
        $filters['sort'] = $request->input('sort', 'created_at');
        $filters['direction'] = $request->input('direction', 'desc');

        $tasks = $this->projectService->getProjectTasks($project, $filters, $with);
        return TaskResource::collection($tasks);
    }

    /**
     * Get project statistics.
     *
     * @param Project $project
     * @return JsonResponse
     */
    public function statistics(Project $project): JsonResponse
    {
        $stats = $this->projectService->getProjectStatistics($project);
        return response()->json($stats);
    }

    /**
     * Add a board to a project.
     *
     * @param Request $request
     * @param Project $project
     * @return BoardResource
     */
    public function addBoard(Request $request, Project $project): BoardResource
    {
        $this->authorize('update', $project);

        $request->validate([
            'board_type_id' => 'required|exists:board_types,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
        ]);

        $attributes = array_filter($request->only(['name', 'description']));

        $board = $this->projectService->addBoard(
            $project,
            $request->input('board_type_id'),
            $attributes
        );

        return new BoardResource($board->load('columns'));
    }

    /**
     * Change the responsible user for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return ProjectResource|JsonResponse
     */
    public function changeResponsibleUser(Request $request, Project $project): ProjectResource|JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'responsible_user_id' => 'required|exists:users,id'
        ]);

        try {
            $userId = $request->input('responsible_user_id');
            $user = User::findOrFail($userId);

            // Check if user is in the same organization
            if ($user->organisation_id != $project->organisation_id) {
                return response()->json([
                    'message' => 'The responsible user must belong to the same organization as the project.'
                ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
            }

            $project->responsible_user_id = $userId;
            $project->save();

            // Ensure the user is also a project member
            $project->users()->syncWithoutDetaching([$userId]);

            $project->load(['team', 'responsibleUser']);
            $project->tasks_count = $project->tasks()->count();

            return new ProjectResource($project);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to change responsible user: ' . $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Upload media files to a project.
     *
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function uploadMedia(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        // Get configuration values
        $maxFiles = config('media.project_documents.max_files', 10);
        $maxFileSize = config('media.project_documents.max_file_size', 20480); // 20MB in KB
        $allowedMimeTypes = config('media.project_documents.allowed_mime_types', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/zip',
            'application/x-rar-compressed'
        ]);

        $collection = $request->input('collection', 'documents');
        
        // Check current document count
        $currentCount = $project->getMedia($collection)->count();
        $newFileCount = count($request->file('files', []));
        
        if (($currentCount + $newFileCount) > $maxFiles) {
            return response()->json([
                'message' => "Cannot upload files. Maximum of {$maxFiles} documents allowed per project. Current: {$currentCount}"
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$request->hasFile('files')) {
            return response()->json([
                'message' => 'No files found in request. Ensure Content-Type is multipart/form-data, not application/json.'
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->validate([
            'files' => "required|array|max:{$newFileCount}",
            'files.*' => [
                'file',
                "max:{$maxFileSize}",
                'mimes:' . implode(',', array_map(function($mime) {
                    return explode('/', $mime)[1];
                }, $allowedMimeTypes))
            ],
            'collection' => 'sometimes|string|in:documents,attachments',
        ]);

        try {
            $uploadedFiles = [];

            foreach ($request->file('files') as $file) {
                $media = $project->addMedia($file)
                    ->usingName($file->getClientOriginalName())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection($collection);

                $uploadedFiles[] = [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'url' => $media->getUrl(),
                    'collection' => $collection,
                ];
            }

            return response()->json([
                'message' => 'Files uploaded successfully',
                'files' => $uploadedFiles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload files: ' . $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Get media files for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function getMedia(Request $request, Project $project): JsonResponse
    {
        $collection = $request->input('collection', 'documents');
        
        $media = $project->getMedia($collection)->map(function ($mediaItem) {
            return [
                'id' => $mediaItem->id,
                'name' => $mediaItem->name,
                'file_name' => $mediaItem->file_name,
                'mime_type' => $mediaItem->mime_type,
                'size' => $mediaItem->size,
                'url' => $mediaItem->getUrl(),
                'collection' => $mediaItem->collection_name,
                'created_at' => $mediaItem->created_at,
            ];
        });

        return response()->json([
            'media' => $media
        ]);
    }

    /**
     * Delete a media file from a project.
     *
     * @param Project $project
     * @param Media $media
     * @return JsonResponse
     */
    public function deleteMedia(Project $project, Media $media): JsonResponse
    {
        $this->authorize('update', $project);

        try {
            // Verify the media belongs to this project
            if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
                return response()->json([
                    'message' => 'Media file not found for this project'
                ], ResponseAlias::HTTP_NOT_FOUND);
            }

            $media->delete();

            return response()->json([
                'message' => 'Media file deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete media file: ' . $e->getMessage()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Download/view a document from a project.
     *
     * @param Project $project
     * @param Media $media
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadDocument(Project $project, Media $media)
    {
        // Check if user is authenticated via API or web
        if (!auth()->guard('api')->check() && !auth()->check()) {
            if (request()->expectsJson() || request()->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        $this->authorize('view', $project);

        // Verify the media belongs to this project
        if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
            abort(404, 'Document not found for this project');
        }

        // Get the full file path
        $filePath = $media->getPath();
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found on disk');
        }

        // Return the file for inline viewing or download
        return response()->file($filePath, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="' . $media->file_name . '"'
        ]);
    }

    /**
     * Get document thumbnail.
     *
     * @param Project $project
     * @param Media $media
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function getDocumentThumbnail(Project $project, Media $media)
    {
        // Check if user is authenticated via API or web
        if (!auth()->guard('api')->check() && !auth()->check()) {
            if (request()->expectsJson() || request()->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        $this->authorize('view', $project);

        // Verify the media belongs to this project
        if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
            abort(404, 'Document not found for this project');
        }

        // Check if thumbnail conversion exists
        if (!$media->hasGeneratedConversion('thumbnail')) {
            abort(404, 'Thumbnail not available for this document');
        }

        $thumbnailPath = $media->getPath('thumbnail');
        
        if (!file_exists($thumbnailPath)) {
            abort(404, 'Thumbnail file not found on disk');
        }

        return response()->file($thumbnailPath, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline'
        ]);
    }

    /**
     * Get document preview.
     *
     * @param Project $project
     * @param Media $media
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function getDocumentPreview(Project $project, Media $media)
    {
        // Check if user is authenticated via API or web
        if (!auth()->guard('api')->check() && !auth()->check()) {
            if (request()->expectsJson() || request()->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        $this->authorize('view', $project);

        // Verify the media belongs to this project
        if ($media->model_id !== $project->id || $media->model_type !== Project::class) {
            abort(404, 'Document not found for this project');
        }

        // Check if preview conversion exists
        if (!$media->hasGeneratedConversion('preview')) {
            abort(404, 'Preview not available for this document');
        }

        $previewPath = $media->getPath('preview');
        
        if (!file_exists($previewPath)) {
            abort(404, 'Preview file not found on disk');
        }

        return response()->file($previewPath, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline'
        ]);
    }
}
