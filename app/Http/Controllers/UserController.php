<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\OrganizationAddedNotification;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->authorizeResource(User::class, 'user', [
            'except' => ['store', 'index', 'profile']
        ]);
    }

    /**
     * Display the authenticated user's profile.
     *
     * @param Request $request
     * @return UserResource
     */
    public function profile(Request $request): UserResource
    {
        $user = $request->user();
        $user->load(['roles', 'permissions', 'organisation']);

        return new UserResource($user);
    }

    /**
     * Display a listing of users.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

        // Filter by organization if specified
        if ($request->has('organisation_id')) {
            $query->whereHas('organisations', function($q) use ($request) {
                $q->where('organisations.id', $request->input('organisation_id'));
            });
        }

        // Filter by team if specified
        if ($request->has('team_id')) {
            $query->whereHas('teams', function($q) use ($request) {
                $q->where('teams.id', $request->input('team_id'));
            });
        }

        // Filter by role if specified
        if ($request->has('role')) {
            $query->role($request->input('role'));
        }

        // Include relationships based on request parameters
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $allowedIncludes = ['roles', 'permissions', 'organisation'];
            $validIncludes = array_intersect($allowedIncludes, $includes);
            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
        } else {
            // Default includes
            $query->with(['roles']);
        }

        // Support for searching
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        // Support for sorting
        if ($request->has('sort')) {
            $sortField = $request->input('sort');
            $sortDirection = $request->input('direction', 'asc');
            $allowedFields = ['name', 'email', 'created_at'];

            if (in_array($sortField, $allowedFields)) {
                $query->orderBy($sortField, $sortDirection);
            }
        } else {
            // Default sort by name
            $query->orderBy('name');
        }

        $users = $query->paginate($request->get('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     *
     * @param StoreUserRequest $request
     * @return UserResource
     * @throws AuthorizationException
     */
    public function store(StoreUserRequest $request): UserResource
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        // If organisation_id is not provided but user has only one organisation,
        // use that organisation's ID (this should be already handled by the request,
        // but we're adding an extra safeguard here)
        if (empty($data['organisation_id']) && $request->user()->organisations()->count() === 1) {
            $organisation = $request->user()->organisations()->first();
            $data['organisation_id'] = $organisation->id;
        }

        $user = User::create($data);

        // Assign role if specified
        if ($request->has('role')) {
            $organisationId = $request->organisation_id ?? null;
            $user->assignRole([$request->role, ['organisation_id' => $organisationId]]);
        } else {
            // Assign default role
            if ($request->has('organisation_id')) {
                $user->assignRole(['guest', ['organisation_id' => $request->organisation_id]]);
            } else {
                $user->assignRole('guest');
            }
        }

        // Assign organization if specified
        if ($request->has('organisation_id')) {
            $orgRole = $request->input('organisation_role', 'member');
            $user->organisations()->attach($request->organisation_id, ['role' => $orgRole]);

            // Also set as primary organization
            $user->update(['organisation_id' => $request->organisation_id]);
        }

        return new UserResource($user->load(['roles', 'permissions', 'organisations']));
    }

    /**
     * Display the specified user.
     *
     * @param Request $request
     * @param User $user
     * @return UserResource
     */
    public function show(Request $request, User $user): UserResource
    {
        // Build includes based on request or defaults
        $includes = ['roles'];

        if ($request->has('include')) {
            $requestedIncludes = explode(',', $request->input('include'));
            $allowedIncludes = ['permissions', 'organisation', 'teams', 'organisations'];
            $additionalIncludes = array_intersect($allowedIncludes, $requestedIncludes);
            $includes = array_merge($includes, $additionalIncludes);
        }

        return new UserResource($user->load($includes));
    }

    /**
     * Update the specified user.
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return UserResource
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        // Handle password update if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        // Update roles if authorized and specified
        if ($request->has('role') && $request->user()->can('manageRoles', $user)) {
            $user->syncRoles([$request->role]);
        }

        // Update primary organization if specified
        if ($request->has('organisation_id')) {
            // Ensure user is a member of this organization
            if (!$user->organisations()->where('organisations.id', $request->organisation_id)->exists()) {
                $orgRole = $request->input('organisation_role', 'member');
                $user->organisations()->attach($request->organisation_id, ['role' => $orgRole]);
            }

            // Set as primary organization
            $user->update(['organisation_id' => $request->organisation_id]);
        }

        return new UserResource($user->load(['roles', 'permissions', 'organisation', 'organisations']));
    }

    /**
     * Remove the specified user.
     *
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user): JsonResponse
    {
        // Check if user has active tasks
        $activeTasksCount = $user->tasksResponsibleFor()->where('status', '!=', 'completed')->count();

        if ($activeTasksCount > 0) {
            return response()->json([
                'message' => 'User has active tasks. Please reassign them before deletion.',
                'active_tasks_count' => $activeTasksCount
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if user is a team lead
        $teamsLedCount = $user->ledTeams()->count();

        if ($teamsLedCount > 0) {
            return response()->json([
                'message' => 'User is a team lead. Please assign new team leads before deletion.',
                'teams_led_count' => $teamsLedCount
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if user owns any organizations
        $ownedOrgsCount = $user->ownedOrganisations()->count();

        if ($ownedOrgsCount > 0) {
            return response()->json([
                'message' => 'User owns organizations. Please transfer ownership before deletion.',
                'owned_orgs_count' => $ownedOrgsCount
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Soft delete the user
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.'
        ]);
    }

    /**
     * Restore a soft-deleted user.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->authorize('restore', $user);

        $user->restore();

        return response()->json([
            'message' => 'User restored successfully.',
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Get user teams.
     *
     * @param Request $request
     * @param User $user
     * @return AnonymousResourceCollection
     */
    public function teams(Request $request, User $user): AnonymousResourceCollection
    {
        $query = $user->teams();

        // Support for searching
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $teams = $query->paginate($request->get('per_page', 15));

        return TeamResource::collection($teams);
    }

    /**
     * Get user tasks.
     *
     * @param Request $request
     * @param User $user
     * @return AnonymousResourceCollection
     */
    public function tasks(Request $request, User $user): AnonymousResourceCollection
    {
        $query = $user->tasksResponsibleFor();

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
            // Default sort by due date
            $query->orderBy('due_date', 'asc');
        }

        $tasks = $query->paginate($request->get('per_page', 15));

        return TaskResource::collection($tasks);
    }

    /**
     * Get user projects.
     *
     * @param Request $request
     * @param User $user
     * @return AnonymousResourceCollection
     */
    public function projects(Request $request, User $user): AnonymousResourceCollection
    {
        $query = $user->projects();

        // Support for searching
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $projects = $query->paginate($request->get('per_page', 15));

        return ProjectResource::collection($projects);
    }

    /**
     * Update user roles.
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', $user);

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        // Get the user's current roles
        $currentRoles = $user->getRoleNames();

        // Sync the roles
        $user->syncRoles($request->roles);

        // Get added and removed roles
        $addedRoles = array_values(array_diff($request->roles, $currentRoles->toArray()));
        $removedRoles = array_values(array_diff($currentRoles->toArray(), $request->roles));

        return response()->json([
            'message' => 'User roles updated successfully.',
            'added_roles' => $addedRoles,
            'removed_roles' => $removedRoles,
            'current_roles' => $user->getRoleNames()
        ]);
    }

    /**
     * Get available roles.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function roles(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::all(['id', 'name']);

        return response()->json([
            'roles' => $roles
        ]);
    }

    /**
     * Invite a user to an organization.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function inviteToOrganisation(Request $request): JsonResponse
    {
        // Validate that the user can invite others to their organization
        $this->authorize('manageMembers', $request->user()->organisation);

        $request->validate([
            'email' => 'required|email',
            'name' => 'sometimes|string|max:255',
            'organisation_id' => 'sometimes|exists:organisations,id',
            'send_invitation' => 'sometimes|boolean',
        ]);

        // Use current user's organization if none specified
        $organisationId = $request->input('organisation_id', $request->user()->organisation_id);

        if (!$organisationId) {
            return response()->json([
                'message' => 'No organization specified and user does not have a default organization'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if the user already exists
        $user = User::where('email', $request->email)->first();
        $isNewUser = !$user;

        // If user doesn't exist, create a new one
        if ($isNewUser) {
            // Generate a temporary password
            $tempPassword = \Str::random(12);

            $user = User::create([
                'email' => $request->email,
                'name' => $request->input('name', ''),
                'password' => Hash::make($tempPassword),
                'organisation_id' => $organisationId,
            ]);
        }

        // Check if the user is already a member of this organization
        $alreadyMember = $user->organisations()
            ->where('organisations.id', $organisationId)
            ->exists();

        if (!$alreadyMember) {
            // Add user to the organization
            $user->organisations()->attach($organisationId);

            // Set as primary organization if user doesn't have one
            if (!$user->organisation_id) {
                $user->update(['organisation_id' => $organisationId]);
            }
        } else {
            return response()->json([
                'message' => 'User is already a member of this organization',
                'user' => new UserResource($user)
            ], Response::HTTP_OK);
        }

        // Find the member role template from the organization
        $memberRoleTemplate = $this->getMemberRoleTemplate($organisationId);

        if ($memberRoleTemplate) {
            // Create role with template for the user in this organization
            $role = Role::firstOrCreate(
                [
                    'name' => 'member',
                    'guard_name' => 'api',
                    'organisation_id' => $organisationId
                ],
                [
                    'template_id' => $memberRoleTemplate->id,
                    'level' => $memberRoleTemplate->level ?? 10,
                ]
            );

            // Assign role to user in organization context
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $role->id,
                    'model_id' => $user->id,
                    'model_type' => get_class($user),
                    'organisation_id' => $organisationId
                ],
                [
                    'role_id' => $role->id // Needed for updateOrInsert
                ]
            );
        }

        // Send invitation email if requested
        if ($request->input('send_invitation', true)) {
            // Send email invitation with reset password link
            if($isNewUser){
                try {
                    $token = Password::createToken($user);
                    $user->notify(new OrganizationInvitationNotification($token, $organisationId));
                } catch (\Exception $e) {
                    \Log::error('Failed to send invitation email: ' . $e->getMessage());
                    // Continue execution even if email fails
                }
            }else{
                try{
                    $user->notify(new OrganizationAddedNotification(Organisation::find($organisationId)));
                }catch (\Exception $e){
                    \Log::error('Failed to send invitation email: ' . $e->getMessage());
                    // Continue execution
                }

            }

        }

        return response()->json([
            'message' => $isNewUser ? 'User invited to organization' : 'Existing user added to organization',
            'user' => new UserResource($user->load(['roles', 'organisations'])),
            'is_new_user' => $isNewUser
        ], Response::HTTP_OK);
    }

    /**
     * Helper method to get the member role template
     *
     * @param int $organisationId
     * @return mixed
     */
    private function getMemberRoleTemplate(int $organisationId)
    {
        // Try to get template from the same organization first
        $template = DB::table('role_templates')
            ->join('roles', 'role_templates.id', '=', 'roles.template_id')
            ->where('roles.name', 'member')
            ->where('roles.organisation_id', $organisationId)
            ->select('role_templates.*', 'roles.level')
            ->first();

        if (!$template) {
            // Fall back to the template from Demo Organization
            $demoOrg = \App\Models\Organisation::where('name', 'Demo Organization')->first();

            if ($demoOrg) {
                $template = DB::table('role_templates')
                    ->join('roles', 'role_templates.id', '=', 'roles.template_id')
                    ->where('roles.name', 'member')
                    ->where('roles.organisation_id', $demoOrg->id)
                    ->select('role_templates.*', 'roles.level')
                    ->first();
            }
        }

        return $template;
    }

    /**
     * Switch the user's active organization.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function switchOrganisation(Request $request): JsonResponse
    {
        $request->validate([
            'organisation_id' => 'required|exists:organisations,id'
        ]);

        $organisationId = $request->organisation_id;
        $user = $request->user();

        // Check if user belongs to this organization
        $isMember = $user->organisations()
            ->where('organisations.id', $organisationId)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'message' => 'You are not a member of this organization'
            ], Response::HTTP_FORBIDDEN);
        }

        // Update the user's active organization
        $user->update(['organisation_id' => $organisationId]);

        // Clear permission cache to reflect new organization context
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Active organization switched successfully',
            'organisation_id' => $organisationId,
            'user' => new UserResource($user->load(['roles', 'permissions', 'organisation']))
        ]);
    }
}
