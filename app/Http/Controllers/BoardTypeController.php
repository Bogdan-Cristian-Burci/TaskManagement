<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardTypeRequest;
use App\Http\Resources\BoardTypeResource;
use App\Models\BoardType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class BoardTypeController extends Controller
{

    public function __construct()
    {
        $this->authorizeResource(BoardType::class, 'boardType');
    }

    public function index(Request $request)
    {
        $query = BoardType::query();

        if ($request->has('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }


        // Add sorting
        $sortField = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // With relationships
        if ($request->boolean('with_boards')) {
            $query->with(['boards']);
        }

        // Pagination
        $boardTypes = $query->paginate($request->get('per_page', 15));

        return BoardTypeResource::collection($boardTypes);
    }

    public function store(BoardTypeRequest $request)
    {
        return new BoardTypeResource(BoardType::create($request->validated()));
    }

    public function show(Request $request, BoardType $boardType)
    {
        $with = [];

        if ($request->boolean('with_boards')) {
            $with[] = 'boards';
        }

        if ($request->boolean('with_history')) {
            $with[] = 'history';
        }

        if (!empty($with)) {
            $boardType->load($with);
        }

        return new BoardTypeResource($boardType->load(['boards']));
    }

    public function update(BoardTypeRequest $request, BoardType $boardType)
    {
        $boardType->update($request->validated());

        return new BoardTypeResource($boardType);
    }

    public function destroy(BoardType $boardType)
    {
        if ($boardType->boards()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete board type that has associated boards.',
                'board_count' => $boardType->boards()->count()
            ], 422);
        }

        $boardType->delete();

        return response()->noContent();
    }
}
