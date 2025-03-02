<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeTypeRequest;
use App\Http\Resources\ChangeTypeResource;
use App\Models\ChangeType;
use App\Models\TaskHistory;
use App\Repositories\Interfaces\ChangeTypeRepositoryInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ChangeTypeController extends Controller
{
    use AuthorizesRequests;
    /**
     * The change type repository instance.
     *
     * @var ChangeTypeRepositoryInterface
     */
    protected ChangeTypeRepositoryInterface $changeTypeRepository;

    /**
     * Create a new controller instance.
     *
     * @param ChangeTypeRepositoryInterface $changeTypeRepository
     */
    public function __construct(ChangeTypeRepositoryInterface $changeTypeRepository)
    {
        $this->changeTypeRepository = $changeTypeRepository;
        $this->authorizeResource(ChangeType::class, 'changeType');
    }

    /**
     * Display a listing of change types.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        if ($request->has('name')) {
            $changeTypes = $this->changeTypeRepository->findAllByPartialName($request->name);
            return ChangeTypeResource::collection($changeTypes);
        }

        if ($request->has('per_page')) {
            $changeTypes = $this->changeTypeRepository->paginate($request->get('per_page', 15));
        } else {
            $changeTypes = $this->changeTypeRepository->all();
        }

        return ChangeTypeResource::collection($changeTypes);
    }

    /**
     * Store a newly created change type.
     *
     * @param ChangeTypeRequest $request
     * @return JsonResponse
     */
    public function store(ChangeTypeRequest $request) : JsonResponse
    {
        $changeType = $this->changeTypeRepository->create($request->validated());

        return (new ChangeTypeResource($changeType))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Display the specified change type.
     *
     * @param ChangeType $changeType
     * @return ChangeTypeResource
     */
    public function show(ChangeType $changeType)
    {
        return new ChangeTypeResource($changeType);
    }

    /**
     * Update the specified change type.
     *
     * @param ChangeTypeRequest $request
     * @param ChangeType $changeType
     * @return ChangeTypeResource
     */
    public function update(ChangeTypeRequest $request, ChangeType $changeType)
    {
        $this->changeTypeRepository->update($changeType, $request->validated());

        // Get a fresh instance with updated data
        $changeType = $this->changeTypeRepository->find($changeType->id);

        return new ChangeTypeResource($changeType);
    }

    /**
     * Remove the specified change type.
     *
     * @param ChangeType $changeType
     * @return JsonResponse
     */
    public function destroy(ChangeType $changeType): JsonResponse
    {
        // Check if the change type is in use in task history
        $taskHistoryCount = TaskHistory::where('field_changed', $changeType->name)->count();
        if ($taskHistoryCount > 0) {
            return response()->json([
                'message' => 'Cannot delete change type that is in use.',
                'task_history_count' => $taskHistoryCount
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->changeTypeRepository->delete($changeType);

        return response()->json([], ResponseAlias::HTTP_NO_CONTENT);
    }

    /**
     * Find change type by name.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function findByName(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $changeType = $this->changeTypeRepository->findByName($request->name);

        if (!$changeType) {
            return response()->json([
                'message' => 'Change type not found.'
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        return (new ChangeTypeResource($changeType))->response();
    }

    /**
     * Clear cache for change types.
     *
     * @return JsonResponse
     */
    public function clearCache()
    {
        $this->authorize('manage', ChangeType::class);

        $this->changeTypeRepository->clearCache();

        return response()->json([
            'message' => 'Change type cache cleared successfully.'
        ]);
    }
}
