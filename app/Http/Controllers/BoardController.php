<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardRequest;
use App\Http\Resources\BoardResource;
use App\Models\Board;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Board::class, 'board');
    }

    public function index(Request $request)
    {
        $query = Board::query();

        // Filter by project
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Add sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        return BoardResource::collection($query->paginate(15));
    }

    public function store(BoardRequest $request)
    {
        return new BoardResource(Board::create($request->validated()));
    }

    public function show(Board $board)
    {
        $board->load(['project', 'boardType', 'tasks']);
        return new BoardResource($board);
    }

    public function update(BoardRequest $request, Board $board)
    {
        $board->update($request->validated());

        return new BoardResource($board);
    }

    public function destroy(Board $board)
    {
        try {
            $board->delete();
            return response()->json(['message' => 'Board deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete board', 'error' => $e->getMessage()], 500);
        }
    }
}
