<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganisationRequest;
use App\Http\Resources\OrganisationResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class OrganisationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->authorizeResource(Organisation::class, 'organisation');
    }

    /**
     * Display a listing of the organisations for the authenticated user.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->organisations();

        // Include relationships based on request parameters
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $allowedIncludes = ['creator', 'owner', 'users', 'teams', 'projects'];
            $validIncludes = array_intersect($allowedIncludes, $includes);
            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
        } else {
            // Default includes
            $query->with(['creator', 'owner']);
        }

        // Support for sorting
        if ($request->has('sort')) {
            $sortField = $request->input('sort');
            $sortDirection = $request->input('direction', 'asc');
            $allowedFields = ['name', 'created_at', 'updated_at'];

            if (in_array($sortField, $allowedFields)) {
                $query->orderBy($sortField, $sortDirection);
            }
        } else {
            // Default sort by name
            $query->orderBy('name');
        }

        // Support for filtering
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        $organisations = $query->paginate($request->get('per_page', 15));

        return OrganisationResource::collection($organisations);
    }

    /**
     * Store a newly created organisation in storage.
     *
     * @param OrganisationRequest $request
     * @return OrganisationResource
     */
    public function store(OrganisationRequest $request): OrganisationResource
    {
        $organisation = new Organisation($request->validated());
        $organisation->created_by = $request->user()->id;

        if (!$request->has('owner_id')) {
            $organisation->owner_id = $request->user()->id;
        }

        $organisation->save();

        // Attach creator as a member with owner role
        $organisation->users()->attach($request->user()->id, ['role' => 'owner']);

        return new OrganisationResource($organisation->load(['creator', 'owner']));
    }

    /**
     * Display the specified organisation.
     *
     * @param Request $request
     * @param Organisation $organisation
     * @return OrganisationResource
     */
    public function show(Request $request, Organisation $organisation): OrganisationResource
    {
        // Build the includes based on request or defaults
        $includes = ['creator', 'owner'];

        if ($request->has('include')) {
            $requestedIncludes = explode(',', $request->input('include'));
            $allowedIncludes = ['users', 'teams', 'projects'];
            $additionalIncludes = array_intersect($allowedIncludes, $requestedIncludes);
            $includes = array_merge($includes, $additionalIncludes);
        }

        return new OrganisationResource(
            $organisation->load($includes)
        );
    }

    /**
     * Update the specified organisation in storage.
     *
     * @param OrganisationRequest $request
     * @param Organisation $organisation
     * @return OrganisationResource
     */
    public function update(OrganisationRequest $request, Organisation $organisation): OrganisationResource
    {
        $organisation->update($request->validated());

        // If owner has changed, update the pivot table role
        if ($request->has('owner_id') && $request->owner_id != $organisation->getOriginal('owner_id')) {
            // Make sure the new owner is in the users table as 'owner'
            $organisation->users()->updateExistingPivot(
                $request->owner_id,
                ['role' => 'owner']
            );

            // If the previous owner is not the creator, downgrade them to 'admin'
            $previousOwnerId = $organisation->getOriginal('owner_id');
            if ($previousOwnerId != $organisation->created_by) {
                $organisation->users()->updateExistingPivot(
                    $previousOwnerId,
                    ['role' => 'admin']
                );
            }
        }

        return new OrganisationResource($organisation->load(['creator', 'owner']));
    }

    /**
     * Remove the specified organisation from storage.
     *
     * @param Organisation $organisation
     * @return JsonResponse
     */
    public function destroy(Organisation $organisation): JsonResponse
    {
        // Check for constraints before deletion
        $projectsCount = $organisation->projects()->count();
        $teamsCount = $organisation->teams()->count();

        if ($projectsCount > 0 || $teamsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete organisation with active projects or teams.',
                'projects_count' => $projectsCount,
                'teams_count' => $teamsCount
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organisation->delete();

        return response()->json([
            'message' => 'Organisation deleted successfully.'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted organisation.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $organisation = Organisation::withTrashed()->findOrFail($id);
        $this->authorize('restore', $organisation);

        $organisation->restore();

        return response()->json([
            'message' => 'Organisation restored successfully.',
            'organisation' => new OrganisationResource($organisation)
        ]);
    }

    /**
     * Display a listing of the organisation's users.
     *
     * @param Request $request
     * @param Organisation $organisation
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function users(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $this->authorize('view', $organisation);

        $query = $organisation->users();

        // Filter by role
        if ($request->has('role')) {
            $query->wherePivot('role', $request->input('role'));
        }

        // Support for searching users
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->get('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Display a listing of the organisation's teams.
     *
     * @param Request $request
     * @param Organisation $organisation
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function teams(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $this->authorize('view', $organisation);

        $query = $organisation->teams();

        // Support for searching teams
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $teams = $query->paginate($request->get('per_page', 15));

        return TeamResource::collection($teams);
    }

    /**
     * Display a listing of the organisation's projects.
     *
     * @param Request $request
     * @param Organisation $organisation
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function projects(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $this->authorize('view', $organisation);

        $query = $organisation->projects();

        // Support for searching projects
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        $projects = $query->paginate($request->get('per_page', 15));

        return ProjectResource::collection($projects);
    }

    /**
     * Add a user to an organisation.
     *
     * @param Request $request
     * @param Organisation $organisation
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function addUser(Request $request, Organisation $organisation): JsonResponse
    {
        $this->authorize('manageMembers', $organisation);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,member'
        ]);

        $user = User::findOrFail($request->input('user_id'));

        // Check if user is already a member
        if ($organisation->hasMember($user)) {
            return response()->json([
                'message' => 'User is already a member of this organisation.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Don't allow setting a role of 'owner' through this endpoint
        $role = $request->input('role');
        if ($role === 'owner') {
            $role = 'admin';
        }

        $organisation->users()->attach($user->id, ['role' => $role]);

        return response()->json([
            'message' => 'User added to organisation successfully.',
            'user' => new UserResource($user),
            'role' => $role
        ], Response::HTTP_OK);
    }

    /**
     * Update a user's role in an organisation.
     *
     * @param Request $request
     * @param Organisation $organisation
     * @param int $userId
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function updateUserRole(Request $request, Organisation $organisation, int $userId): JsonResponse
    {
        $this->authorize('manageMembers', $organisation);

        $request->validate([
            'role' => 'required|in:admin,member'
        ]);

        $user = User::findOrFail($userId);

        // Check if user is a member
        if (!$organisation->hasMember($user)) {
            return response()->json([
                'message' => 'User is not a member of this organisation.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prevent changing the role of the owner
        if ($organisation->owner_id === $userId) {
            return response()->json([
                'message' => 'Cannot change the role of the organisation owner.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update the user's role
        $organisation->users()->updateExistingPivot($userId, [
            'role' => $request->input('role')
        ]);

        return response()->json([
            'message' => 'User role updated successfully.',
            'user' => new UserResource($user),
            'role' => $request->input('role')
        ], Response::HTTP_OK);
    }

    /**
     * Remove a user from an organisation.
     *
     * @param Organisation $organisation
     * @param int $userId
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function removeUser(Organisation $organisation, int $userId): JsonResponse
    {
        $this->authorize('manageMembers', $organisation);

        $user = User::findOrFail($userId);

        // Prevent removing the owner
        if ($organisation->owner_id === $userId) {
            return response()->json([
                'message' => 'Cannot remove the organisation owner. Transfer ownership first.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if user is a member
        if (!$organisation->hasMember($user)) {
            return response()->json([
                'message' => 'User is not a member of this organisation.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organisation->users()->detach($userId);

        return response()->json([
            'message' => 'User removed from organisation successfully.'
        ], Response::HTTP_OK);
    }

    /**
     * Transfer ownership of an organisation.
     *
     * @param Request $request
     * @param Organisation $organisation
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function transferOwnership(Request $request, Organisation $organisation): JsonResponse
    {
        $this->authorize('changeOwner', $organisation);

        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $newOwnerId = $request->input('user_id');
        $currentOwnerId = $organisation->owner_id;

        // Prevent transferring to the current owner
        if ($newOwnerId === $currentOwnerId) {
            return response()->json([
                'message' => 'User is already the owner of this organisation.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if new owner is a member
        if (!$organisation->hasMember($newOwnerId)) {
            return response()->json([
                'message' => 'New owner must be a member of the organisation.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update the owner_id in the organisation
        $organisation->owner_id = $newOwnerId;
        $organisation->save();

        // Update roles in the pivot table
        $organisation->users()->updateExistingPivot($newOwnerId, ['role' => 'owner']);
        $organisation->users()->updateExistingPivot($currentOwnerId, ['role' => 'admin']);

        return response()->json([
            'message' => 'Organisation ownership transferred successfully.',
            'organisation' => new OrganisationResource($organisation->load('owner'))
        ], Response::HTTP_OK);
    }
}

