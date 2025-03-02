<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatusRequest;
use App\Http\Resources\StatusResource;
use App\Models\Status;
use App\Repositories\Interfaces\StatusRepositoryInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class StatusController extends Controller
{

    use AuthorizesRequests;
    /**
     * The status repository instance.
     *
     * @var StatusRepositoryInterface
     */
    protected StatusRepositoryInterface $statusRepository;

    /**
     * Create a new controller instance.
     *
     * @param StatusRepositoryInterface $statusRepository
     */
    public function __construct(StatusRepositoryInterface $statusRepository)
    {
        $this->statusRepository = $statusRepository;
        $this->authorizeResource(Status::class, 'status');
    }

    /**
     * Display a listing of statuses.
     *
     * @return AnonymousResourceCollection
     */
    public function index() : AnonymousResourceCollection
    {
        $statuses = $this->statusRepository->all();

        return StatusResource::collection($statuses);
    }

    /**
     * Store a newly created status.
     *
     * @param StatusRequest $request
     * @return JsonResponse
     */
    public function store(StatusRequest $request) : JsonResponse
    {
        $status = $this->statusRepository->create($request->validated());

        return (new StatusResource($status))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Display the specified status.
     *
     * @param Status $status
     * @return StatusResource
     */
    public function show(Status $status) : StatusResource
    {
        if (request()->boolean('with_tasks_count')) {
            $status->loadCount('tasks');
        }

        return new StatusResource($status);
    }

    /**
     * Update the specified status.
     *
     * @param StatusRequest $request
     * @param Status $status
     * @return StatusResource
     */
    public function update(StatusRequest $request, Status $status) : StatusResource
    {
        // Check if another status is already default and we're setting this one to default
        if ($request->has('is_default') && $request->boolean('is_default') && !$status->is_default) {
            // Get the current default status and unset it
            $defaultStatus = $this->statusRepository->getDefault();
            if ($defaultStatus && $defaultStatus->id !== $status->id) {
                $this->statusRepository->update($defaultStatus, ['is_default' => false]);
            }
        }

        $this->statusRepository->update($status, $request->validated());

        // Get a fresh instance with updated data
        $status = $this->statusRepository->find($status->id);

        return new StatusResource($status);
    }

    /**
     * Remove the specified status.
     *
     * @param Status $status
     * @return JsonResponse
     */
    public function destroy(Status $status) : JsonResponse
    {
        // Check if the status is in use
        if ($status->tasks()->exists()) {
            return response()->json([
                'message' => 'Cannot delete status that is in use.',
                'tasks_count' => $status->tasks()->count()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if this is the default status
        if ($status->is_default) {
            return response()->json([
                'message' => 'Cannot delete the default status.',
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->statusRepository->delete($status);

        return response()->json([], ResponseAlias::HTTP_NO_CONTENT);
    }

    /**
     * Find status by name.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function findByName(Request $request) : JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $status = $this->statusRepository->findByName($request->name);

        if (!$status) {
            return response()->json([
                'message' => 'Status not found.'
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        return (new StatusResource($status))->response();
    }

    /**
     * Get the default status.
     *
     * @return JsonResponse
     */
    public function getDefault() : JsonResponse
    {
        $status = $this->statusRepository->getDefault();

        if (!$status) {
            return response()->json([
                'message' => 'No default status found.'
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        return (new StatusResource($status))->response();
    }

    /**
     * Clear cache for statuses.
     *
     * @return JsonResponse
     */
    public function clearCache() : JsonResponse
    {
        $this->authorize('manage', Status::class);

        $this->statusRepository->clearCache();

        return response()->json([
            'message' => 'Status cache cleared successfully.'
        ]);
    }

    /**
     * Reorder statuses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reorder(Request $request) : JsonResponse
    {
        $this->authorize('manage', Status::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:statuses,id'
        ]);

        // Update position for each status
        foreach ($request->ids as $index => $id) {
            $status = $this->statusRepository->find($id);
            if ($status) {
                $this->statusRepository->update($status, ['position' => $index + 1]);
            }
        }

        return response()->json([
            'message' => 'Statuses reordered successfully.'
        ]);
    }

    /**
     * Get statuses by category.
     *
     * @param string $category
     * @return AnonymousResourceCollection
     */
    public function getByCategory(string $category): AnonymousResourceCollection
    {
        $statuses = $this->statusRepository->findAllBy('category', $category);
        return StatusResource::collection($statuses);
    }
}
