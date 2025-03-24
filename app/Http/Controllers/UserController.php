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
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        // Remove authorizeResource and handle permissions explicitly per action
    }

    /**
     * Display a listing of users.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     * @throws AuthorizationException
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('user.view', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view users.');
        }

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
     * @throws AuthorizationException|\Exception
     */
    public function store(StoreUserRequest $request): UserResource
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        // If organisation_id is not provided but user has only one organisation,
        // use that organisation's ID
        if (empty($data['organisation_id']) && $request->user()->organisations()->count() === 1) {
            $organisation = $request->user()->organisations()->first();
            $data['organisation_id'] = $organisation->id;
        }

        $user = User::create($data);

        // Assign organization if specified
        if (!empty($data['organisation_id'])) {
            $orgRole = $request->input('organisation_role', 'member');
            $user->organisations()->attach($data['organisation_id'], ['role' => $orgRole]);

            // Also set as primary organization
            $user->update(['organisation_id' => $data['organisation_id']]);

            // Assign role if specified - using the correct signature
            if (!empty($data['role'])) {
                try {
                    // This uses the correct signature: assignRole(string $templateName, int $organisationId)
                    $user->assignRole($data['role'], $data['organisation_id']);
                } catch (\Exception $e) {
                    \Log::error('Failed to assign role: ' . $e->getMessage());
                    // Fallback to default role if custom role assignment fails
                    $user->assignRole(get_default_role(), $data['organisation_id']);
                }
            } else {
                // Assign default role using correct signature
                $user->assignRole(get_default_role(), $data['organisation_id']);
            }
            $user->syncPivotRoleWithFormalRole($data['organisation_id']);
        }

        return new UserResource($user->load(['roles', 'organisations']));
    }

    /**
     * Display the specified user.
     *
     * @param Request $request
     * @param User $user
     * @return UserResource
     * @throws AuthorizationException
     */
    public function show(Request $request, User $user): UserResource
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('user.view', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view this user.');
        }

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
     * @throws AuthorizationException
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {

        $data = $request->validated();

        // Handle password update if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return new UserResource($user->load(['roles', 'organisation', 'organisations']));
    }

    /**
     * Remove the specified user.
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('user.delete', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to delete users.');
        }

        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if user has active tasks
        $activeTasksCount = $user->tasksResponsibleFor()->incomplete()->count();

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
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('user.restore', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to restore users.');
        }

        $user = User::withTrashed()->findOrFail($id);
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
     * @throws AuthorizationException
     */
    public function teams(Request $request, User $user): AnonymousResourceCollection
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('team.view', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view teams.');
        }

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
     * @throws AuthorizationException
     */
    public function tasks(Request $request, User $user): AnonymousResourceCollection
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('task.view', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view tasks.');
        }

        // If viewing tasks of another user, require additional permission
        if ($request->user()->id !== $user->id &&
            !$request->user()->hasPermission('tasks.view.all', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view other users\' tasks.');
        }

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
     * @throws AuthorizationException
     */
    public function projects(Request $request, User $user): AnonymousResourceCollection
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('project.view', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view projects.');
        }

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
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('roles.assign', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to manage user roles.');
        }

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:role_templates,name'
        ]);

        // Get the user's current roles in this organization
        $organisationId = $request->user()->organisation_id;
        $currentRoles = $user->getOrganisationRoles($organisationId)
            ->map(function ($role) {
                return $role->template->name ?? null;
            })
            ->filter()
            ->toArray();

        // Remove existing roles in this organization context
        DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->where('organisation_id', $organisationId)
            ->delete();

        // Add the new roles using the correct signature
        $addedRoles = [];
        foreach ($request->roles as $roleName) {
            try {
                $user->assignRole($roleName, $organisationId);
                $user->syncPivotRoleWithFormalRole($organisationId);
                $addedRoles[] = $roleName;
            } catch (\Exception $e) {
                \Log::error("Failed to assign role {$roleName}: " . $e->getMessage());
            }
        }

        // Calculate removed roles
        $removedRoles = array_values(array_diff($currentRoles, $addedRoles));

        // Get current roles after update
        $newRoles = $user->getOrganisationRoles($organisationId)
            ->map(function ($role) {
                return $role->template->name ?? null;
            })
            ->filter()
            ->toArray();

        return response()->json([
            'message' => 'User roles updated successfully.',
            'added_roles' => $addedRoles,
            'removed_roles' => $removedRoles,
            'current_roles' => $newRoles
        ]);
    }

    /**
     * Get available roles.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function roles(Request $request): JsonResponse
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('roles.view', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view roles.');
        }

        // Get role templates for the current organization
        $templates = DB::table('role_templates')
            ->join('roles', 'role_templates.id', '=', 'roles.template_id')
            ->where('roles.organisation_id', $request->user()->organisation_id)
            ->orWhereNull('roles.organisation_id')
            ->select('role_templates.id', 'role_templates.name', 'roles.level')
            ->distinct()
            ->get();

        return response()->json([
            'roles' => $templates
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
        // Get organization ID - use specified or fall back to user's current org
        $organisationId = $request->input('organisation_id', $request->user()->organisation_id);

        if (!$organisationId) {
            return response()->json([
                'message' => 'No organization specified and user does not have a default organization'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('users.invite', $organisationId)) {
            throw new AuthorizationException('You do not have permission to invite users to this organization.');
        }

        $request->validate([
            'email' => 'required|email',
            'name' => 'sometimes|string|max:255',
            'organisation_id' => 'sometimes|exists:organisations,id',
            'send_invitation' => 'sometimes|boolean',
            'role' => 'sometimes|string|exists:role_templates,name',
        ]);

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

        // Assign role to the user if specified, otherwise use default member role
        $roleName = $request->input('role', 'member');

        try {
            // Using the correct signature for assignRole
            $user->assignRole($roleName, $organisationId);
        } catch (\Exception $e) {
            \Log::error('Failed to assign role during invitation: ' . $e->getMessage());

            // Try to assign the default member role as fallback
            try {
                $user->assignRole('member', $organisationId);
            } catch (\Exception $innerE) {
                \Log::error('Failed to assign fallback role: ' . $innerE->getMessage());
            }
        } finally {
            $user->syncPivotRoleWithFormalRole($organisationId);
        }

        // Send invitation email if requested
        if ($request->input('send_invitation', true)) {
            // Send email invitation with reset password link
            if ($isNewUser) {
                try {
                    $token = Password::createToken($user);
                    $user->notify(new OrganizationInvitationNotification($token, $organisationId));
                } catch (\Exception $e) {
                    \Log::error('Failed to send invitation email: ' . $e->getMessage());
                    // Continue execution even if email fails
                }
            } else {
                try {
                    $user->notify(new OrganizationAddedNotification(Organisation::find($organisationId)));
                } catch (\Exception $e) {
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

        $organisationId = (int) $request->organisation_id;
        $user = $request->user();

        // Get the actual database value to avoid middleware confusion
        $databaseOrgId = \DB::table('users')->where('id', $user->id)->value('organisation_id');

        if ($databaseOrgId === $organisationId) {
            return response()->json([
                'message' => 'User already has this organization active'
            ], Response::HTTP_OK);
        }

        // Check if user belongs to this organization
        $isMember = $user->organisations()
            ->where('organisations.id', $organisationId)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'message' => 'You are not a member of this organization'
            ], Response::HTTP_FORBIDDEN);
        }

        // Update the user's active organization in the database
        $updateResult = \DB::table('users')
            ->where('id', $user->id)
            ->update(['organisation_id' => $organisationId]);

        // Also update the session
        Session::put('active_organisation_id', $organisationId);

        if (!$updateResult) {
            return response()->json([
                'message' => 'Failed to switch organization',
                'debug_info' => [
                    'old_org_id' => $user->getOriginal('organisation_id'),
                    'attempted_new_org_id' => $organisationId
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Reload the user to ensure we have the latest data
        $user = $user->fresh();

        return response()->json([
            'message' => 'Active organization switched successfully',
            'organisation_id' => $organisationId,
            'user' => new UserResource($user->load(['roles', 'organisation']))
        ]);
    }
}
