<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeTypeRequest;
use App\Http\Resources\ChangeTypeResource;
use App\Models\ChangeType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ChangeTypeController extends Controller
{
    use AuthorizesRequests;
    public function __construct()
    {
        $this->authorizeResource(ChangeType::class, 'changeType');
    }

    public function index(Request $request)
    {
        $query = ChangeType::query();

        if ($request->has('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        $changeTypes = $query->paginate($request->get('per_page', 15));

        return ChangeTypeResource::collection($changeTypes);
    }

    public function store(ChangeTypeRequest $request)
    {
        return new ChangeTypeResource(ChangeType::create($request->validated()));
    }

    public function show(ChangeType $changeType)
    {
        return new ChangeTypeResource($changeType);
    }

    public function update(ChangeTypeRequest $request, ChangeType $changeType)
    {
        $changeType->update($request->validated());
        return new ChangeTypeResource($changeType);
    }

    public function destroy(ChangeType $changeType)
    {
        $changeType->delete();
        return response()->noContent();
    }
}
