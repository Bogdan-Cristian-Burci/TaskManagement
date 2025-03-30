<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardRequest;
use App\Http\Resources\BoardColumnResource;
use App\Http\Resources\BoardResource;
use App\Http\Resources\TaskResource;
use App\Models\Board;
use App\Models\Project;
use App\Services\BoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BoardController extends Controller
{
    protected BoardService $boardService;

    public function __construct(BoardService $boardService)
    {
        $this->boardService = $boardService;
        $this->authorizeResource(Board::class, 'board');
    }

    /**
     * Display a listing of the boards.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [];
        $with = ['project'];

        // Add filters from request parameters
        if ($request->has('project_id')) {
            $filters['project_id'] = $request->project_id;
        }

        if ($request->has('board_type_id')) {
            $filters['board_type_id'] = $request->board_type_id;
        }

        // Add relationships to load
        if ($request->has('with_columns')) {
            $with[] = 'columns';
        }

        if ($request->has('with_active_sprint')) {
            $with[] = 'activeSprint';
        }

        $boards = $this->boardService->getBoards($filters, $with);

        return BoardResource::collection($boards);
    }

    /**
     * Store a newly created board.
     *
     * @param BoardRequest $request
     * @return BoardResource
     */
    public function store(BoardRequest $request): BoardResource
    {
        $validated = $request->validated();

        $project = Project::findOrFail($validated['project_id']);
        $boardTypeId = $validated['board_type_id'];

        // Remove these fields as they're passed separately to createBoard
        unset($validated['project_id'], $validated['board_type_id']);

        $board = $this->boardService->createBoard(
            $project,
            $boardTypeId,
            $validated
        );

        return new BoardResource($board->load('columns'));
    }

    /**
     * Display the specified board.
     *
     * @param Request $request
     * @param Board $board
     * @return BoardResource
     */
    public function show(Request $request, Board $board): BoardResource
    {
        $with = ['project', 'boardType'];

        // Add relationships to load
        if ($request->has('with_columns')) {
            $with[] = 'columns';
        }

        if ($request->has('with_active_sprint')) {
            $with[] = 'activeSprint';
        }

        $board->load($with);

        return new BoardResource($board);
    }

    /**
     * Update the specified board.
     *
     * @param BoardRequest $request
     * @param Board $board
     * @return BoardResource
     */
    public function update(BoardRequest $request, Board $board): BoardResource
    {
        $board = $this->boardService->updateBoard($board, $request->validated());
        return new BoardResource($board);
    }

    /**
     * Remove the specified board.
     *
     * @param Request $request
     * @param Board $board
     * @return JsonResponse
     */
    public function destroy(Request $request, Board $board): JsonResponse
    {
        $cascadeDelete = $request->boolean('cascade_delete', false);

        $this->boardService->deleteBoard($board, $cascadeDelete);

        return response()->json(['message' => 'Board deleted successfully']);
    }

    /**
     * Duplicate a board.
     *
     * @param Request $request
     * @param Board $board
     * @return BoardResource
     */
    public function duplicate(Request $request, Board $board): BoardResource
    {
        $this->authorize('duplicate', $board);

        $newName = $request->input('name');
        $newBoard = $this->boardService->duplicateBoard($board, $newName);

        return new BoardResource($newBoard);
    }

    /**
     * Get columns for a board.
     *
     * @param Board $board
     * @return AnonymousResourceCollection
     */
    public function columns(Board $board): AnonymousResourceCollection
    {
        return BoardColumnResource::collection($board->columns);
    }

    /**
     * Get tasks for a board.
     *
     * @param Request $request
     * @param Board $board
     * @return AnonymousResourceCollection
     */
    public function tasks(Request $request, Board $board): AnonymousResourceCollection
    {
        $filters = [];
        $with = ['status', 'assignee', 'priority', 'boardColumn'];

        // Add filters from request parameters
        if ($request->has('status_id')) {
            $filters['status_id'] = $request->status_id;
        }

        if ($request->has('column_id')) {
            $filters['column_id'] = $request->column_id;
        }

        if ($request->has('assignee_id')) {
            $filters['assignee_id'] = $request->assignee_id;
        }

        $tasks = $this->boardService->getBoardTasks($board, $filters, $with);

        return TaskResource::collection($tasks);
    }

    /**
     * Get statistics for a board.
     *
     * @param Board $board
     * @return JsonResponse
     */
    public function statistics(Board $board): JsonResponse
    {
        $stats = $this->boardService->getBoardStatistics($board);

        return response()->json($stats);
    }
}
