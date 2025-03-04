<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskTypeRequest;
use App\Http\Resources\TaskTypeResource;
use App\Models\TaskType;
use App\Repositories\Interfaces\TaskTypeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class TaskTypeController extends Controller
{
    /**
     * The task type repository instance.
     *
     * @var TaskTypeRepositoryInterface
     */
    protected TaskTypeRepositoryInterface $taskTypeRepository;

    /**
     * Create a new controller instance.
     *
     * @param TaskTypeRepositoryInterface $taskTypeRepository
     */
    public function __construct(TaskTypeRepositoryInterface $taskTypeRepository)
    {
        $this->taskTypeRepository = $taskTypeRepository;
        $this->authorizeResource(TaskType::class, 'taskType');
    }

    /**
     * Display a listing of task types.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request) : AnonymousResourceCollection
    {
        if ($request->boolean('with_tasks_count')) {
            $taskTypes = $this->taskTypeRepository->getWithTaskCount();
        } else {
            $taskTypes = $this->taskTypeRepository->all();
        }

        return TaskTypeResource::collection($taskTypes);
    }

    /**
     * Store a newly created task type.
     *
     * @param TaskTypeRequest $request
     * @return JsonResponse
     */
    public function store(TaskTypeRequest $request) : JsonResponse
    {
        $taskType = $this->taskTypeRepository->create($request->validated());

        return (new TaskTypeResource($taskType))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    /**
     * Display the specified task type.
     *
     * @param TaskType $taskType
     * @return TaskTypeResource
     */
    public function show(TaskType $taskType) : TaskTypeResource
    {
        // We could use $this->taskTypeRepository->find($taskType->id) here,
        // but Laravel route model binding is more efficient in this case

        if (request()->boolean('with_tasks_count')) {
            $taskType->loadCount('tasks');
        }

        return new TaskTypeResource($taskType);
    }

    /**
     * Update the specified task type.
     *
     * @param TaskTypeRequest $request
     * @param TaskType $taskType
     * @return TaskTypeResource
     */
    public function update(TaskTypeRequest $request, TaskType $taskType) : TaskTypeResource
    {
        $this->taskTypeRepository->update($taskType, $request->validated());

        // Get a fresh instance with updated data
        $taskType = $this->taskTypeRepository->find($taskType->id);

        return new TaskTypeResource($taskType);
    }

    /**
     * Remove the specified task type.
     *
     * @param TaskType $taskType
     * @return JsonResponse
     */
    public function destroy(TaskType $taskType) : JsonResponse
    {
        // Check if the task type is in use
        if ($taskType->tasks()->exists()) {
            return response()->json([
                'message' => 'Cannot delete task type that is in use.',
                'tasks_count' => $taskType->tasks()->count()
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->taskTypeRepository->delete($taskType);

        return response()->json([], ResponseAlias::HTTP_NO_CONTENT);
    }

    /**
     * Find task type by name.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function findByName(Request $request) : JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $taskType = $this->taskTypeRepository->findByName($request->name);

        if (!$taskType) {
            return response()->json([
                'message' => 'Task type not found.'
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        return (new TaskTypeResource($taskType))->response();
    }

    /**
     * Clear cache for task types.
     *
     * @return JsonResponse
     */
    public function clearCache() : JsonResponse
    {
        $this->authorize('manage', TaskType::class);

        $this->taskTypeRepository->clearCache();

        return response()->json([
            'message' => 'Task type cache cleared successfully.'
        ]);
    }
}
