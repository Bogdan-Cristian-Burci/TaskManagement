<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeTypeRequest;
use App\Http\Resources\ChangeTypeResource;
use App\Models\ChangeType;
use App\Services\ChangeTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ChangeTypeController extends Controller
{
    /**
     * The change type service instance.
     *
     * @var ChangeTypeService
     */
    protected ChangeTypeService $changeTypeService;

    /**
     * Create a new controller instance.
     *
     * @param ChangeTypeService $changeTypeService
     */
    public function __construct(ChangeTypeService $changeTypeService)
    {
        $this->changeTypeService = $changeTypeService;
        $this->authorizeResource(ChangeType::class, 'changeType');
    }

    /**
     * Display a listing of change types.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [];

        if ($request->boolean('with_task_histories_count')) {
            $filters['with_task_histories_count'] = true;
        }

        if ($request->has('name')) {
            $filters['name'] = $request->name;
        }

        $changeTypes = $this->changeTypeService->getAllChangeTypes($filters);

        return ChangeTypeResource::collection($changeTypes);
    }

    /**
     * Store a newly created change type.
     *
     * @param ChangeTypeRequest $request
     * @return JsonResponse
     */
    public function store(ChangeTypeRequest $request): JsonResponse
    {
        $changeType = $this->changeTypeService->createChangeType($request->validated());

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
    public function show(ChangeType $changeType): ChangeTypeResource
    {
        $withCount = request()->boolean('with_task_histories_count');

        if ($withCount) {
            $changeType = $this->changeTypeService->getChangeType($changeType->id, true);
        }

        return new ChangeTypeResource($changeType);
    }

    /**
     * Update the specified change type.
     *
     * @param ChangeTypeRequest $request
     * @param ChangeType $changeType
     * @return ChangeTypeResource
     */
    public function update(ChangeTypeRequest $request, ChangeType $changeType): ChangeTypeResource
    {
        $changeType = $this->changeTypeService->updateChangeType($changeType, $request->validated());
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
        try {
            $this->changeTypeService->deleteChangeType($changeType);
            return response()->json([], ResponseAlias::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'task_history_count' => strpos($e->getMessage(), 'in use') !== false ?
                    $this->changeTypeService->getUsageCount($changeType) : 0
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }
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

        $changeType = $this->changeTypeService->findByName($request->name);

        if (!$changeType) {
            return response()->json([
                'message' => 'Change type not found.'
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        return (new ChangeTypeResource($changeType))->response();
    }

    /**
     * Sync task histories with change types based on field_changed values.
     *
     * @return JsonResponse
     */
    public function syncTaskHistories(): JsonResponse
    {
        $this->authorize('manage', ChangeType::class);

        $updated = $this->changeTypeService->syncTaskHistories();

        return response()->json([
            'message' => 'Task histories synced successfully.',
            'updated_records' => $updated
        ]);
    }

    /**
     * Clear cache for change types.
     *
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        $this->authorize('manage', ChangeType::class);

        $this->changeTypeService->clearCache();

        return response()->json([
            'message' => 'Change type cache cleared successfully.'
        ]);
    }
}
