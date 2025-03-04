<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatusTransitionRequest;
use App\Http\Resources\StatusTransitionResource;
use App\Models\Status;
use App\Models\StatusTransition;
use App\Repositories\Interfaces\StatusTransitionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class StatusTransitionController extends Controller
{

    /**
     * The status transition repository instance.
     *
     * @var StatusTransitionRepositoryInterface
     */
    protected StatusTransitionRepositoryInterface $statusTransitionRepository;

    /**
     * Create a new controller instance.
     *
     * @param StatusTransitionRepositoryInterface $statusTransitionRepository
     */
    public function __construct(StatusTransitionRepositoryInterface $statusTransitionRepository)
    {
        $this->statusTransitionRepository = $statusTransitionRepository;
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of status transitions.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', Status::class);

        if ($request->has('board_id')) {
            $transitions = $this->statusTransitionRepository->findForBoard($request->board_id);
        } else {
            $transitions = $this->statusTransitionRepository->all();
        }

        return StatusTransitionResource::collection($transitions);
    }

    /**
     * Store a newly created status transition.
     *
     * @param StatusTransitionRequest $request
     * @return JsonResponse
     */
    public function store(StatusTransitionRequest $request): JsonResponse
    {
        $this->authorize('manage', Status::class);

        $statusTransition = $this->statusTransitionRepository->create($request->validated());

        return (new StatusTransitionResource($statusTransition))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Display the specified status transition.
     *
     * @param StatusTransition $statusTransition
     * @return StatusTransitionResource
     */
    public function show(StatusTransition $statusTransition): StatusTransitionResource
    {
        $this->authorize('manage', Status::class);

        $statusTransition->load(['fromStatus', 'toStatus', 'board']);

        return new StatusTransitionResource($statusTransition);
    }

    /**
     * Update the specified status transition.
     *
     * @param StatusTransitionRequest $request
     * @param StatusTransition $statusTransition
     * @return StatusTransitionResource
     */
    public function update(StatusTransitionRequest $request, StatusTransition $statusTransition): StatusTransitionResource
    {
        $this->authorize('manage', Status::class);

        $this->statusTransitionRepository->update($statusTransition, $request->validated());

        // Get a fresh instance with updated data
        $statusTransition = $this->statusTransitionRepository->find($statusTransition->id);
        $statusTransition->load(['fromStatus', 'toStatus', 'board']);

        return new StatusTransitionResource($statusTransition);
    }

    /**
     * Remove the specified status transition.
     *
     * @param StatusTransition $statusTransition
     * @return Response
     */
    public function destroy(StatusTransition $statusTransition): Response
    {
        $this->authorize('manage', Status::class);

        $this->statusTransitionRepository->delete($statusTransition);

        return response()->noContent();
    }

    /**
     * Get all possible transitions from a status.
     *
     * @param Request $request
     * @param Status $status
     * @return AnonymousResourceCollection
     */
    public function getFromStatus(Request $request, Status $status): AnonymousResourceCollection
    {
        $boardId = $request->input('board_id');

        $transitions = $this->statusTransitionRepository->findFromStatus($status->id, $boardId);

        return StatusTransitionResource::collection($transitions);
    }

    /**
     * Check if a transition between two statuses is valid.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function isValidTransition(Request $request): JsonResponse
    {
        $request->validate([
            'from_status_id' => 'required|exists:statuses,id',
            'to_status_id' => 'required|exists:statuses,id',
            'board_id' => 'nullable|exists:boards,id',
        ]);

        $transition = $this->statusTransitionRepository->findBetweenStatuses(
            $request->from_status_id,
            $request->to_status_id,
            $request->board_id
        );

        return response()->json([
            'valid' => $transition !== null,
            'transition' => $transition ? new StatusTransitionResource($transition) : null,
        ]);
    }

    /**
     * Clear cache for status transitions.
     *
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        $this->authorize('manage', Status::class);

        $this->statusTransitionRepository->clearCache();

        return response()->json([
            'message' => 'Status transition cache cleared successfully.'
        ]);
    }
}
