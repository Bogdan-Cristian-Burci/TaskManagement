<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganisationRequest;
use App\Http\Resources\OrganisationResource;
use App\Models\Organisation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganisationController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Organisation::class, 'organisation');
    }
    public function index(Request $request): AnonymousResourceCollection
    {
        $organisations = $request->user()->organisations()
            ->with(['creator', 'owner'])
            ->paginate($request->get('per_page', 15));

        return OrganisationResource::collection($organisations);
    }

    public function store(OrganisationRequest $request):OrganisationResource
    {
        $organisation = new Organisation($request->validated());
        $organisation->created_by = auth()->id();

        if (!$request->has('owner_id')) {
            $organisation->owner_id = auth()->id();
        }

        $organisation->save();

        // Attach creator as member
        $organisation->users()->attach(auth()->id(), ['role' => 'owner']);

        return new OrganisationResource($organisation->load(['creator', 'owner']));
    }

    public function show(Organisation $organisation): OrganisationResource
    {
        return new OrganisationResource(
            $organisation->load(['creator', 'owner', 'users', 'teams', 'projects'])
        );
    }

    public function update(OrganisationRequest $request, Organisation $organisation):OrganisationResource
    {
        $organisation->update($request->validated());

        return new OrganisationResource($organisation->load(['creator', 'owner']));
    }

    // not to be shown in the documentation, user should have only one Organisation and should not be able to delete it
    public function destroy(Organisation $organisation)
    {
        $organisation->delete();

        return response()->noContent();
    }
}
