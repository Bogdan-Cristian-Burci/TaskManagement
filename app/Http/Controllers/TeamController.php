<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamMemberRequest;
use App\Http\Requests\TeamRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Scopes\OrganizationScope;
use App\Models\Team;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the teams.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Get organization context
        $organizationId = OrganizationContext::getCurrentOrganizationId();

        // Use the team model's query which already has the global scope applied
        $query = Team::query();

        // Include relationships based on request parameters
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $allowedIncludes = ['organisation', 'teamLead', 'users', 'projects'];
            $validIncludes = array_intersect($allowedIncludes, $includes);
            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
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

        // Support for filtering by organization
        if ($request->has('organisation_id')) {
            $query->where('organisation_id', $request->input('organisation_id'));
        }

        // Support for filtering by team lead
        if ($request->has('team_lead_id')) {
            $query->where('team_lead_id', $request->input('team_lead_id'));
        }

        // Support for searching
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $teams = $query->paginate($request->get('per_page', 15));

        return TeamResource::collection($teams);
    }

    /**
     * Display a listing of all teams across organizations (admin only).
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function indexAll(Request $request): AnonymousResourceCollection
    {
        // Use the model method that ignores the organization scope
        $query = Team::allOrganizations();

        // Apply additional filters as needed
        if ($request->has('organisation_id')) {
            $query->where('organisation_id', $request->input('organisation_id'));
        }

        // Rest of your filtering logic...

        $teams = $query->paginate($request->get('per_page', 15));

        return TeamResource::collection($teams);
    }

    /**
     * Store a newly created team.
     *
     * @param TeamRequest $request
     * @return TeamResource
     */
    public function store(TeamRequest $request): TeamResource
    {

        // Create the team from validated data
        $team = Team::create($request->validated());

        // Add the creator to the team if not already included
        if (!$team->hasMember($request->user())) {
            $team->users()->attach($request->user()->id);
        }

        return new TeamResource($team->load(['organisation', 'teamLead']));
    }

    /**
     * Display the specified team.
     *
     * @param Request $request
     * @param Team $team
     * @return TeamResource
     * @throws AuthorizationException
     */
    public function show(Request $request, Team $team): TeamResource
    {
        $this->authorize('view', $team);

        // Build includes based on request or defaults
        $includes = ['organisation', 'teamLead'];

        if ($request->has('include')) {
            $requestedIncludes = explode(',', $request->input('include'));
            $allowedIncludes = ['users', 'projects'];
            $additionalIncludes = array_intersect($allowedIncludes, $requestedIncludes);
            $includes = array_merge($includes, $additionalIncludes);
        }

        return new TeamResource($team->load($includes));
    }

    /**
     * Display a specific team, ignoring organization scope (admin only).
     *
     * @param Request $request
     * @param $id
     * @return TeamResource
     */
    public function showAll(Request $request, $id): TeamResource
    {
        // Manually find the team without the organization scope
        $team = Team::withoutGlobalScope(OrganizationScope::class)->findOrFail($id);

        return new TeamResource($team->load(['organisation', 'teamLead', 'users']));
    }

    /**
     * Update the specified team.
     *
     * @param TeamRequest $request
     * @param Team $team
     * @return TeamResource
     * @throws AuthorizationException
     */
    public function update(TeamRequest $request, Team $team): TeamResource
    {
        $this->authorize('update', $team);

        $team->update($request->validated());

        // If team lead has changed, ensure they are a member of the team
        if ($request->has('team_lead_id') && $request->team_lead_id != $team->getOriginal('team_lead_id')) {
            if (!$team->hasMember($request->team_lead_id)) {
                $team->users()->attach($request->team_lead_id);
            }
        }

        return new TeamResource($team->load(['organisation', 'teamLead']));
    }

    /**
     * Remove the specified team.
     *
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        // Check for constraints before deletion
        $projectsCount = $team->projects()->count();

        if ($projectsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete team with active projects. Please reassign or delete projects first.',
                'projects_count' => $projectsCount
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $team->delete();

        return response()->json([
            'message' => 'Team deleted successfully.'
        ], Response::HTTP_OK);
    }

    /**
     * Restore a soft-deleted team.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $team = Team::withTrashed()->findOrFail($id);
        $this->authorize('restore', $team);

        $team->restore();

        return response()->json([
            'message' => 'Team restored successfully.',
            'team' => new TeamResource($team)
        ]);
    }

    /**
     * Get team members.
     *
     * @param Request $request
     * @param Team $team
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function members(Request $request, Team $team): AnonymousResourceCollection
    {
        $this->authorize('view', $team);

        $query = $team->users();

        // Support for searching
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $members = $query->paginate($request->get('per_page', 15));

        return UserResource::collection($members);
    }

    /**
     * Get team projects.
     *
     * @param Request $request
     * @param Team $team
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function projects(Request $request, Team $team): AnonymousResourceCollection
    {
        $this->authorize('view', $team);

        $query = $team->projects();

        // Include tasks if requested
        if ($request->has('with_tasks')) {
            $query->with('tasks');
        }

        $projects = $query->paginate($request->get('per_page', 15));

        return ProjectResource::collection($projects);
    }

    /**
     * Add members to a team.
     *
     * @param TeamMemberRequest $request
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function addMembers(TeamMemberRequest $request, Team $team): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        $userIds = $request->validated()['user_ids'];

        // Filter out users who are already team members
        $newUserIds = [];
        foreach ($userIds as $userId) {
            if (!$team->hasMember($userId)) {
                $newUserIds[] = $userId;
            }
        }

        if (empty($newUserIds)) {
            return response()->json([
                'message' => 'All specified users are already team members.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Make sure all users belong to the same organization
        $organisationId = $team->organisation_id;
        $validUserIds = User::whereIn('id', $newUserIds)
            ->whereHas('organisations', function($query) use ($organisationId) {
                $query->where('organisations.id', $organisationId);
            })
            ->pluck('id')
            ->toArray();

        if (empty($validUserIds)) {
            return response()->json([
                'message' => 'No valid users to add to the team. Users must belong to the team\'s organization.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $team->users()->attach($validUserIds);

        return response()->json([
            'message' => count($validUserIds) . ' users added to the team successfully.'
        ], Response::HTTP_OK);
    }

    /**
     * Remove members from a team.
     *
     * @param TeamMemberRequest $request
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function removeMembers(TeamMemberRequest $request, Team $team): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        $userIds = $request->validated()['user_ids'];

        // Don't allow removing the team lead
        if (in_array($team->team_lead_id, $userIds)) {
            return response()->json([
                'message' => 'Cannot remove the team lead. Assign a new team lead first.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Filter users that are actually team members
        $existingUserIds = $team->users()
            ->whereIn('users.id', $userIds)
            ->pluck('users.id')
            ->toArray();

        if (empty($existingUserIds)) {
            return response()->json([
                'message' => 'None of the specified users are team members.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $team->users()->detach($existingUserIds);

        return response()->json([
            'message' => count($existingUserIds) . ' users removed from the team successfully.'
        ], Response::HTTP_OK);
    }

    /**
     * Change the team lead.
     *
     * @param Request $request
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function changeTeamLead(Request $request, Team $team): JsonResponse
    {
        $this->authorize('changeTeamLead', $team);

        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $newTeamLeadId = $request->input('user_id');

        $newTeamLead = User::find($newTeamLeadId);

        if(!$newTeamLead) {
            return response()->json([
                'message' => 'User not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        if(!$newTeamLead->isMemberOf($team->organisation_id)){
            return response()->json([
                'message' => 'User does not belong to the team\'s organization.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if new team lead is a member of the team
        if (!$team->hasMember($newTeamLeadId)) {
            // Add the new team lead to the team if they're not already a member
            $team->users()->attach($newTeamLeadId);
        }

        // Update the team lead
        $team->team_lead_id = $newTeamLeadId;
        $team->save();

        return response()->json([
            'message' => 'Team lead changed successfully.',
            'team' => new TeamResource($team->load(['teamLead']))
        ], Response::HTTP_OK);
    }

    /**
     * Get tasks assigned to the team.
     *
     * @param Request $request
     * @param Team $team
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function tasks(Request $request, Team $team): AnonymousResourceCollection
    {
        $this->authorize('view', $team);

        $query = $team->tasks();

        // Include relationships based on request parameters
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $allowedIncludes = ['project', 'assignedUser', 'comments'];
            $validIncludes = array_intersect($allowedIncludes, $includes);
            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // Filter by due date
        if ($request->has('due_date')) {
            $dueDate = $request->input('due_date');
            $query->whereDate('due_date', '=', $dueDate);
        }

        // Support for sorting
        if ($request->has('sort')) {
            $sortField = $request->input('sort');
            $sortDirection = $request->input('direction', 'asc');
            $allowedFields = ['title', 'status', 'priority', 'due_date', 'created_at'];

            if (in_array($sortField, $allowedFields)) {
                $query->orderBy($sortField, $sortDirection);
            }
        } else {
            // Default sort by due date ascending (nearest first)
            $query->orderBy('due_date', 'asc');
        }

        $tasks = $query->paginate($request->get('per_page', 15));

        return TaskResource::collection($tasks);
    }
}
