<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardRequest;
use App\Http\Resources\BoardResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\BoardColumnResource;
use App\Models\Board;
use App\Models\Project;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BoardController extends Controller
{

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Board::class, 'board', [
            'except' => ['index', 'projectBoards', 'store']
        ]);
    }

    /**
     * Display a listing of the boards.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Board::class);

        $query = Board::query();

        // Filter by project
        if ($request->has('project_id')) {
            $projectId = $request->input('project_id');

            // Verify user has access to this project
            $project = Project::findOrFail($projectId);
            $this->authorize('view', $project);

            $query->byProject($projectId);
        } else {
            // If no project specified, only show boards from projects the user has access to
            $projectIds = $request->user()->projects()->pluck('projects.id')->toArray();
            $query->whereIn('project_id', $projectIds);
        }

        // Filter by board type
        if ($request->has('board_type_id')) {
            $query->byType($request->input('board_type_id'));
        }

        // Filter by archive status
        if ($request->has('archived')) {
            $query->where('is_archived', $request->boolean('archived'));
        }

        // Filter by name search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by recent activity
        if ($request->has('with_recent_activity')) {
            $days = $request->input('days', 7);
            $query->withRecentActivity($days);
        }

        // Include relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['project', 'boardType', 'columns'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $query->with($include);
                }
            }
        }

        // Include counts
        if ($request->boolean('with_counts', false)) {
            $query->withCount(['tasks', 'columns']);
        }

        // Sort by field
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $validSortFields = ['name', 'created_at', 'updated_at'];

        if (in_array($sortField, $validSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $boards = $query->paginate($request->input('per_page', 15));

        return BoardResource::collection($boards);
    }

    /**
     * Get boards for a specific project.
     */
    public function projectBoards(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $query = $project->boards();

        // Filter by board type
        if ($request->has('board_type_id')) {
            $query->where('board_type_id', $request->input('board_type_id'));
        }

        // Filter by archive status
        if ($request->has('archived')) {
            $query->where('is_archived', $request->boolean('archived'));
        } else {
            // Default to non-archived boards
            $query->where('is_archived', false);
        }

        // Include relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['boardType', 'columns'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $query->with($include);
                }
            }
        }

        // Include counts
        if ($request->boolean('with_counts', false)) {
            $query->withCount(['tasks', 'columns']);
        }

        // Sort by field
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');

        $boards = $query->orderBy($sortField, $sortDirection)->get();

        return BoardResource::collection($boards);
    }

    /**
     * Store a newly created board in storage.
     */
    public function store(BoardRequest $request): BoardResource
    {
        $this->authorize('create', Board::class);

        $board = Board::create($request->validated());

        // Create default columns if board type has predefined columns
        $boardType = $board->boardType;
        if ($boardType && $boardType->template) {
            // Use template's columns_structure
            $columnsStructure = $boardType->template->columns_structure ?? [];

            foreach ($columnsStructure as $index => $column) {
                $board->columns()->create([
                    'name' => $column['name'],
                    'position' => $index + 1,
                    'color' => $column['color'] ?? '#6C757D',
                    'wip_limit' => $column['wip_limit'] ?? null,
                    'maps_to_status_id' => $column['status_id'] ?? null,
                ]);
            }
        }

        // Load the relationships needed for the response
        $board->load(['project', 'boardType', 'columns']);

        return new BoardResource($board);
    }

    /**
     * Display the specified board.
     */
    public function show(Request $request, Board $board): BoardResource
    {
        // Load relationships if requested
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['project', 'boardType', 'columns'];

            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $board->load($include);
                }
            }
        }

        // Load counts if requested
        if ($request->boolean('with_counts', false)) {
            $board->loadCount(['tasks', 'columns']);
        }

        return new BoardResource($board);
    }

    /**
     * Update the specified board in storage.
     */
    public function update(BoardRequest $request, Board $board): BoardResource
    {
        $board->update($request->validated());

        // Load the relationships needed for the response
        $board->load(['project', 'boardType', 'columns']);

        return new BoardResource($board);
    }

    /**
     * Remove the specified board from storage.
     */
    public function destroy(Board $board): Response
    {
        // Check if board has tasks before deletion
        if ($board->tasks()->count() > 0) {
            return response([
                'message' => 'Cannot delete board that has associated tasks. Please remove tasks first.',
                'tasks_count' => $board->tasks()->count()
            ], HttpResponse::HTTP_CONFLICT);
        }

        $board->delete();

        return response()->noContent();
    }

    /**
     * Archive the specified board.
     */
    public function archive(Board $board): JsonResponse
    {
        $this->authorize('archive', $board);

        $board->archive();

        return response()->json([
            'message' => 'Board archived successfully',
            'board' => new BoardResource($board)
        ]);
    }

    /**
     * Unarchive the specified board.
     */
    public function unarchive(Board $board): JsonResponse
    {
        $this->authorize('unarchive', $board);

        $board->unarchive();

        return response()->json([
            'message' => 'Board unarchived successfully',
            'board' => new BoardResource($board)
        ]);
    }

    /**
     * Duplicate the specified board.
     */
    public function duplicate(Request $request, Board $board): BoardResource
    {
        $this->authorize('duplicate', $board);

        $newName = $request->input('name');
        $duplicatedBoard = $board->duplicate($newName);

        // Load the relationships needed for the response
        $duplicatedBoard->load(['project', 'boardType', 'columns']);

        return new BoardResource($duplicatedBoard);
    }

    /**
     * Get columns for a board.
     */
    public function columns(Board $board): AnonymousResourceCollection
    {
        return BoardColumnResource::collection($board->columns()->orderBy('position')->get());
    }

    /**
     * Get tasks for a board.
     */
    public function tasks(Request $request, Board $board): AnonymousResourceCollection
    {
        $query = $board->tasks();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by column
        if ($request->has('column_id')) {
            $query->where('board_column_id', $request->input('column_id'));
        }

        // Filter by assignee
        if ($request->has('assignee_id')) {
            $query->where('assignee_id', $request->input('assignee_id'));
        }

        // Include relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $validIncludes = ['assignee', 'reporter', 'subtasks', 'tags'];
            foreach ($includes as $include) {
                if (in_array($include, $validIncludes)) {
                    $query->with($include);
                }
            }
        }

        // Sort by field
        $sortField = $request->input('sort', 'position');
        $sortDirection = $request->input('direction', 'asc');
        $validSortFields = ['title', 'status', 'priority', 'created_at', 'updated_at', 'position'];

        if (in_array($sortField, $validSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('position', 'asc');
        }

        $tasks = $query->paginate($request->input('per_page', 15));

        return TaskResource::collection($tasks);
    }

    /**
     * Restore a soft-deleted board.
     */
    public function restore(int $id): BoardResource
    {
        $board = Board::withTrashed()->findOrFail($id);
        $this->authorize('restore', $board);

        $board->restore();

        return new BoardResource($board->load(['project', 'boardType', 'columns']));
    }

    /**
     * Get board statistics.
     */
    public function statistics(Board $board): JsonResponse
    {
        // Tasks by status
        $tasksByStatus = $board->tasks()
            ->select('status', \DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Tasks by assignee
        $tasksByAssignee = $board->tasks()
            ->select('assignee_id', \DB::raw('count(*) as count'))
            ->groupBy('assignee_id')
            ->pluck('count', 'assignee_id')
            ->toArray();

        // Tasks by column
        $tasksByColumn = $board->tasks()
            ->select('board_column_id', \DB::raw('count(*) as count'))
            ->groupBy('board_column_id')
            ->pluck('count', 'board_column_id')
            ->toArray();

        // Total tasks
        $totalTasks = array_sum($tasksByStatus);

        // Completed tasks
        $completedTasks = $tasksByStatus['completed'] ?? 0;

        // Completion percentage
        $completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        // Recent activity
        $recentActivity = $board->tasks()
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'updated_at', 'assignee_id'])
            ->toArray();

        return response()->json([
            'tasks_by_status' => $tasksByStatus,
            'tasks_by_assignee' => $tasksByAssignee,
            'tasks_by_column' => $tasksByColumn,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'completion_percentage' => $completionPercentage,
            'recent_activity' => $recentActivity
        ]);
    }
}
