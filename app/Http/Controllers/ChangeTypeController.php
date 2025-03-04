<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeTypeRequest;
use App\Http\Resources\ChangeTypeResource;
use App\Models\ChangeType;
use App\Models\TaskHistory;
use App\Repositories\Interfaces\ChangeTypeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ChangeTypeController extends Controller
{
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
    public function index(Request $request) : AnonymousResourceCollection
    {
        if ($request->boolean('with_task_histories_count')) {
            $changeTypes = $this->changeTypeRepository->findWithTaskHistoriesCount();
            return ChangeTypeResource::collection($changeTypes);
        }

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
    public function show(ChangeType $changeType) : ChangeTypeResource
    {
        if (request()->boolean('with_task_histories_count')) {
            // Count both direct relationships and name-based relationships
            $changeType->loadCount(['taskHistories', 'taskHistoriesByName']);
            $changeType->task_histories_count = $changeType->task_histories_count ?? 0;
            $changeType->task_histories_count += $changeType->task_histories_by_name_count;
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
    public function update(ChangeTypeRequest $request, ChangeType $changeType)
    {
        $oldName = $changeType->name;
        $this->changeTypeRepository->update($changeType, $request->validated());

        // If name changed, update task histories to use the new change type ID
        if ($oldName !== $changeType->name && $request->has('name')) {
            // Update the task histories that used the old name
            TaskHistory::where('field_changed', $oldName)
                ->update(['field_changed' => $changeType->name]);
        }

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
        $taskHistoryCount = TaskHistory::where('field_changed', $changeType->name)
            ->orWhere('change_type_id', $changeType->id)
            ->count();

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
     * Sync task histories with change types based on field_changed values.
     * This is a utility method to fix data inconsistencies after adding the change_type_id field.
     *
     * @return JsonResponse
     */
    public function syncTaskHistories(): JsonResponse
    {
        $this->authorize('manage', ChangeType::class);

        $changeTypes = $this->changeTypeRepository->all(['id', 'name']);
        $updated = 0;

        foreach ($changeTypes as $changeType) {
            $count = TaskHistory::where('field_changed', $changeType->name)
                ->whereNull('change_type_id')
                ->update(['change_type_id' => $changeType->id]);

            $updated += $count;
        }

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
    public function clearCache()
    {
        $this->authorize('manage', ChangeType::class);

        $this->changeTypeRepository->clearCache();

        return response()->json([
            'message' => 'Change type cache cleared successfully.'
        ]);
    }
}
