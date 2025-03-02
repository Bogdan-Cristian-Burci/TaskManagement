<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriorityRequest;
use App\Http\Resources\PriorityResource;
use App\Models\Priority;
use App\Repositories\Interfaces\PriorityRepositoryInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class PriorityController extends Controller
{
    use AuthorizesRequests;
    /**
     * The priority repository instance.
     *
     * @var PriorityRepositoryInterface
     */
    protected PriorityRepositoryInterface $priorityRepository;

    /**
     * Create a new controller instance.
     *
     * @param PriorityRepositoryInterface $priorityRepository
     */
    public function __construct(PriorityRepositoryInterface $priorityRepository)
    {
        $this->priorityRepository = $priorityRepository;
        $this->authorizeResource(Priority::class, 'priority');
    }

    /**
     * Display a listing of priorities.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        $priorities = $this->priorityRepository->all();

        return PriorityResource::collection($priorities);
    }

    /**
     * Store a newly created priority.
     *
     * @param PriorityRequest $request
     * @return JsonResponse
     */
    public function store(PriorityRequest $request) : JsonResponse
    {
        $priority = $this->priorityRepository->create($request->validated());

        return (new PriorityResource($priority))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Display the specified priority.
     *
     * @param Priority $priority
     * @return PriorityResource
     */
    public function show(Priority $priority)
    {
        if (request()->boolean('with_tasks_count')) {
            $priority->loadCount('tasks');
        }

        return new PriorityResource($priority);
    }

    /**
     * Update the specified priority.
     *
     * @param PriorityRequest $request
     * @param Priority $priority
     * @return PriorityResource
     */
    public function update(PriorityRequest $request, Priority $priority)
    {
        $this->priorityRepository->update($priority, $request->validated());

        // Get a fresh instance with updated data
        $priority = $this->priorityRepository->find($priority->id);

        return new PriorityResource($priority);
    }

    /**
     * Remove the specified priority.
     *
     * @param Priority $priority
     * @return JsonResponse
     */
    public function destroy(Priority $priority) : JsonResponse
    {
        // Check if the priority is in use
        if ($priority->tasks()->exists()) {
            return response()->json([
                'message' => 'Cannot delete priority that is in use.',
                'tasks_count' => $priority->tasks()->count()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->priorityRepository->delete($priority);

        return response()->json([], ResponseAlias::HTTP_NO_CONTENT);
    }

    /**
     * Find priority by level.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function findByLevel(Request $request) : JsonResponse
    {
        $request->validate([
            'level' => 'required|integer|min:1'
        ]);

        $priority = $this->priorityRepository->findByLevel($request->level);

        if (!$priority) {
            return response()->json([
                'message' => 'Priority not found.'
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        return (new PriorityResource($priority))->response();
    }

    /**
     * Reorder priorities.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('manage', Priority::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:priorities,id'
        ]);

        $success = $this->priorityRepository->reorder($request->ids);

        if (!$success) {
            return response()->json([
                'message' => 'Failed to reorder priorities.'
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Priorities reordered successfully.'
        ]);
    }

    /**
     * Clear cache for priorities.
     *
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        $this->authorize('manage', Priority::class);

        $this->priorityRepository->clearCache();

        return response()->json([
            'message' => 'Priority cache cleared successfully.'
        ]);
    }
}
