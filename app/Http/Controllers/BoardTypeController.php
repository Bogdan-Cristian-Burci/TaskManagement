<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardTypeRequest;
use App\Http\Resources\BoardTypeResource;
use App\Models\BoardType;
use App\Services\BoardTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BoardTypeController extends Controller
{
    protected BoardTypeService $boardTypeService;

    public function __construct(BoardTypeService $boardTypeService)
    {
        $this->boardTypeService = $boardTypeService;
    }

    /**
     * Display a listing of board types.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BoardType::class);

        $filters = [];
        $with = [];
        $perPage = $request->get('per_page', 15);

        // Build filters
        if ($request->has('name')) {
            $filters['name'] = $request->name;
        }

        // Set sorting
        $filters['sort_by'] = $request->get('sort_by', 'name');
        $filters['sort_direction'] = $request->get('sort_direction', 'asc');

        // Load relationships
        if ($request->boolean('with_boards')) {
            $with[] = 'boards';
        }

        $boardTypes = $this->boardTypeService->getBoardTypes($filters, $with, true, $perPage);

        return BoardTypeResource::collection($boardTypes);
    }

    /**
     * Store a newly created board type.
     *
     * @param BoardTypeRequest $request
     * @return BoardTypeResource
     */
    public function store(BoardTypeRequest $request): BoardTypeResource
    {
        $this->authorize('create');
        $boardType = $this->boardTypeService->createBoardType($request->validated());
        return new BoardTypeResource($boardType);
    }

    /**
     * Display the specified board type.
     *
     * @param Request $request
     * @param BoardType $boardType
     * @return BoardTypeResource
     */
    public function show(Request $request, BoardType $boardType): BoardTypeResource
    {
        $this->authorize('view', $boardType);
        $with = [];

        if ($request->boolean('with_boards')) {
            $with[] = 'boards';
        }

        if ($request->boolean('with_history')) {
            $with[] = 'history';
        }

        if (!empty($with)) {
            $boardType = $this->boardTypeService->getBoardType($boardType->id, $with);
        }

        return new BoardTypeResource($boardType);
    }

    /**
     * Update the specified board type.
     *
     * @param BoardTypeRequest $request
     * @param BoardType $boardType
     * @return BoardTypeResource
     */
    public function update(BoardTypeRequest $request, BoardType $boardType): BoardTypeResource
    {
        $this->authorize('update', $boardType);
        $boardType = $this->boardTypeService->updateBoardType($boardType, $request->validated());
        return new BoardTypeResource($boardType);
    }

    /**
     * Remove the specified board type from storage.
     *
     * @param BoardType $boardType
     * @return Response|JsonResponse
     */
    public function destroy(BoardType $boardType): Response|JsonResponse
    {
        $this->authorize('delete', $boardType);

        try {
            $this->boardTypeService->deleteBoardType($boardType);
            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'board_count' => $this->boardTypeService->getBoardCount($boardType)
            ], 422);
        }
    }

    /**
     * Create or retrieve a board type with a specific template.
     *
     * @param Request $request
     * @return BoardTypeResource
     */
    public function getOrCreateWithTemplate(Request $request): BoardTypeResource
    {
        $request->validate([
            'template_id' => 'required|exists:board_templates,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $boardType = $this->boardTypeService->getOrCreateWithTemplate(
            $request->input('template_id'),
            $request->input('name'),
            $request->input('description')
        );

        return new BoardTypeResource($boardType);
    }
}
