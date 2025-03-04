<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardColumnRequest;
use App\Http\Resources\BoardColumnResource;
use App\Models\BoardColumn;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class BoardColumnController extends Controller
{

    public function __construct()
    {
        $this->authorizeResource(BoardColumn::class, 'boardColumn');
    }

    public function index(Request $request)
    {
        $query = BoardColumn::query();

        if ($request->has('board_id')) {
            $query->where('board_id', $request->board_id);
        }

        if ($request->has('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        // Add relationships that are used in the resource
        $query->with(['board', 'tasks']);

        // Add sorting
        $sortField = $request->get('sort_by', 'position');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $boardColumns = $query->paginate($request->get('per_page', 15));

        return BoardColumnResource::collection($boardColumns);
    }

    public function store(BoardColumnRequest $request)
    {
        return new BoardColumnResource(BoardColumn::create($request->validated()));
    }

    public function show(BoardColumn $boardColumn)
    {
        return new BoardColumnResource($boardColumn->load(['board', 'tasks']));
    }

    public function update(BoardColumnRequest $request, BoardColumn $boardColumn)
    {
        $boardColumn->update($request->validated());

        return new BoardColumnResource($boardColumn);
    }

    public function destroy(BoardColumn $boardColumn)
    {
        $boardColumn->delete();

        return response()->noContent();
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'columns' => 'required|array',
            'columns.*.id' => 'required|exists:board_columns,id',
            'columns.*.position' => 'required|integer|min:0'
        ]);

        foreach ($request->columns as $column) {
            BoardColumn::find($column['id'])->update(['position' => $column['position']]);
        }

        return response()->noContent();
    }

    /**
     * Check if adding a task would exceed the column's WIP limit
     */
    public function checkWipLimit(Request $request, BoardColumn $boardColumn)
    {
        $this->authorize('view', $boardColumn);

        $isAtLimit = $boardColumn->isAtWipLimit();

        return response()->json([
            'is_at_limit' => $isAtLimit,
            'wip_limit' => $boardColumn->wip_limit,
            'current_count' => $boardColumn->tasks()->count(),
        ]);
    }
}
