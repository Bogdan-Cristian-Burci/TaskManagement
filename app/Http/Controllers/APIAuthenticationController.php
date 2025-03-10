<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\LoginUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class APIAuthenticationController extends Controller
{

    /**
     * Register a new user.
     *
     * @param CreateUserRequest $request
     * @return JsonResponse
     */
    public function register(CreateUserRequest $request): JsonResponse
    {
        // Validate request (already handled by CreateUserRequest)
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // Create new user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Create a unique organization name
            $baseName = $user->name . "'s Workspace";
            $organisationName = $this->generateUniqueOrganizationName($baseName);

            // Create personal organization
            $organisation = Organisation::create([
                'name' => $organisationName,
                'description' => 'Personal workspace created during registration',
                'unique_id' => Str::slug($organisationName) . '-' . Str::random(6),
                'owner_id' => $user->id
            ]);

            // IMPORTANT: Set organisation_id BEFORE assigning roles
            $user->organisation_id = $organisation->id;
            $user->save();

            // Associate user with organization
            $user->organisations()->attach($organisation->id);

            // Create admin role
            $adminRole = Role::firstOrCreate(
                [
                    'name' => 'admin',
                    'guard_name' => 'api',
                    'organisation_id' => $organisation->id,
                ],
                [
                    'level' => 80,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Assign permissions to role
            $this->assignPermissionsToRole($adminRole);

            // SAFER WAY: Directly insert role assignment to avoid trait issues
            $roleAssigned = DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id,
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'organisation_id' => $organisation->id,
            ]);

            \Log::info('Role assigned directly', [
                'user_id' => $user->id,
                'role_id' => $adminRole->id,
                'organisation_id' => $organisation->id,
                'success' => $roleAssigned
            ]);

            // Clear permission cache
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            // Fresh user with all relationships and direct DB roles
            $user = User::with(['permissions', 'organisation'])->find($user->id);

            // Get roles directly for accurate representation
            $directRoles = DB::table('roles')
                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->where('model_has_roles.organisation_id', $organisation->id)
                ->pluck('roles.name')
                ->toArray();

            // Add roles array to user object for the resource
            $user->directRoles = $directRoles;

            // For registration response, set user resolver for proper authorization
            $request->setUserResolver(function() use ($user) {
                return $user;
            });

            // Log role state after assignment
            \Log::info('Role assignment check', [
                'user_id' => $user->id,
                'direct_roles' => $directRoles,
                'has_admin' => $user->hasRole('admin'),
                'organisation_id' => $user->organisation_id,
            ]);

            // Create token
            $token = $user->createToken('Registration Token', ['read-user'])->accessToken;

            DB::commit();

            // Log successful registration
            Log::info('User registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => request()->ip()
            ]);

            // Set the user directly in the request to ensure it's available in the UserResource
            app('request')->setUserResolver(function () use ($user) {
                return $user;
            });
            return response()->json([
                'message' => 'Registration successful',
                'token' => $token,
                'user' => new UserResource($user),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Log in a user with enhanced security.
     *
     * @param LoginUserRequest $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        // Validate request (already handled by LoginUserRequest)
        $credentials = $request->validated();

        // Attempt authentication
        if (!Auth::attempt($credentials)) {
            // Log failed login attempt (helpful for detecting brute force attempts)
            Log::warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => request()->ip()
            ]);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ])->status(Response::HTTP_UNAUTHORIZED);
        }

        // Get the authenticated user
        $user = User::where('email', $request->email)->first();

        // Verify the password again for extra security
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if the account is locked
        if ($user->locked_at && $user->locked_at > now()->subMinutes(config('auth.lockout_duration', 30))) {
            $unlockTime = $user->locked_at->addMinutes(config('auth.lockout_duration', 30));
            $remainingMinutes = now()->diffInMinutes($unlockTime);

            return response()->json([
                'message' => "Your account is locked. Please try again after {$remainingMinutes} minutes."
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if email is verified when required
        if (config('auth.verify_email', true) && !$user->hasVerifiedEmail()) {
            // Send verification email if not already sent recently
            $user->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Email verification required. A verification link has been sent to your email address.',
                'email_verified' => false
            ], Response::HTTP_FORBIDDEN);
        }

        // Record the login
        $user->recordLogin($request->ip());

        // Check if user has 2FA enabled
        if ($user->hasTwoFactorEnabled()) {
            // Generate a temporary token for 2FA verification
            $temporaryToken = $user->createToken('Two-Factor Auth', ['two-factor'])->accessToken;

            return response()->json([
                'message' => 'Two-factor authentication required',
                'two_factor_required' => true,
                'temp_token' => $temporaryToken
            ], Response::HTTP_OK);
        }

        // Clear permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Create new token with appropriate scopes
        $token = $user->createToken('Login Token', ['read-user'])->accessToken;

        // Load necessary relationships
        $user->load(['roles', 'permissions', 'organisation']);

        // Log successful login
        Log::info('User logged in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip()
        ]);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => new UserResource($user),
        ], Response::HTTP_OK);
    }

    /**
     * Log out a user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        // Store user ID for logging
        $userId = $request->user()->id;

        // Revoke the token that was used to authenticate the current request
        $request->user()->token()->revoke();

        // Log the logout (optional but helpful for security audits)
        Log::info('User logged out', [
            'user_id' => $userId,
            'ip' => request()->ip()
        ]);

        return response()->json([
            'message' => 'Successfully logged out'
        ], Response::HTTP_OK);
    }

    /**
     * Get the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        // Load necessary relationships
        $user = $request->user()->load(['roles', 'permissions', 'organisation', 'teams', 'projects']);

        return response()->json(new UserResource($user), Response::HTTP_OK);
    }

    /**
     * Refresh the access token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refreshToken(Request $request): JsonResponse
    {
        // Get the user from the request
        $user = $request->user();

        // Create new token
        $token = $user->createToken('Refreshed Token', ['read-user'])->accessToken;

        // Log the token refresh (optional)
        Log::info('Token refreshed', [
            'user_id' => $user->id,
            'ip' => request()->ip()
        ]);

        return response()->json([
            'message' => 'Token refreshed successfully',
            'token' => $token
        ], Response::HTTP_OK);
    }

    /**
     * Generate a unique organization name
     *
     * @param string $baseName
     * @return string
     */
    private function generateUniqueOrganizationName(string $baseName): string
    {
        $name = $baseName;
        $counter = 1;

        // Check if the organization name already exists
        while (Organisation::where('name', $name)->exists()) {
            $name = $baseName . ' ' . $counter;
            $counter++;
        }

        return $name;
    }

    /**
     * Assign permissions to a role
     *
     * @param Role $role
     */
    private function assignPermissionsToRole($role)
    {
        // Log the role we're assigning permissions to
        \Log::info('Assigning permissions to admin role', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'organisation_id' => $role->organisation_id,
        ]);

        // List of all permissions for admin
        $permissions = [
            // User permissions
            'user.view', 'user.create', 'user.update', 'user.delete', 'user.viewAny', 'user.forceDelete', 'user.restore',

            // Organisation permissions
            'organisation.view', 'organisation.create', 'organisation.update', 'organisation.delete', 'organisation.viewAny', 'organisation.forceDelete',
            'organisation.restore', 'organisation.inviteUser', 'organisation.removeUser', 'organisation.assignRole', 'organisation.viewMetrics',
            'organisation.manageSettings', 'organisation.exportData',

            // Project permissions
            'project.view', 'project.create', 'project.update', 'project.delete', 'project.viewAny', 'project.forceDelete', 'project.restore',
            'project.addMember', 'project.removeMember', 'project.changeOwner',

            // Team permissions
            'team.view', 'team.create', 'team.update', 'team.delete',

            // Task permissions
            'task.view', 'task.create', 'task.update', 'task.delete', 'task.viewAny', 'task.forceDelete', 'task.restore',
            'task.assign', 'task.changeStatus', 'task.changePriority', 'task.addLabel','task.removeLabel', 'task.moveTask',
            'task.attachFile', 'task.detachFile',

            // Comment permissions
            'comment.view', 'comment.create', 'comment.update', 'comment.delete', 'comment.viewAny', 'comment.forceDelete', 'comment.restore',

            // Role and permission management
            'role.view', 'role.create', 'role.update', 'role.delete',
            'permission.view', 'permission.assign',

            //Boards permissions
            'board.view', 'board.create', 'board.update', 'board.delete', 'board.viewAny', 'board.forceDelete', 'board.restore',
            'board.reorderColumns', 'board.addColumn', 'board.changeColumSettings',

            //Status permissions
            'status.view', 'status.create', 'status.update', 'status.delete', 'status.viewAny', 'status.forceDelete', 'status.restore',

            // Priority permissions
            'priority.view', 'priority.create', 'priority.update', 'priority.delete', 'priority.viewAny', 'priority.forceDelete', 'priority.restore',

            //TaskType
            'taskType.view', 'taskType.create', 'taskType.update', 'taskType.delete', 'taskType.viewAny', 'taskType.forceDelete', 'taskType.restore',

            //Attachment permissions
            'attachment.view', 'attachment.create', 'attachment.update', 'attachment.delete', 'attachment.viewAny', 'attachment.forceDelete', 'attachment.restore',

            //Notification permissions
            'notification.view', 'notification.create', 'notification.update', 'notification.delete', 'notification.viewAny', 'notification.forceDelete', 'notification.restore',
        ];

        // Create and assign each permission
        foreach ($permissions as $permission) {
            // Create the permission in the database if it doesn't exist
            $permissionModel = \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);

            // Assign it to the role
            $role->givePermissionTo($permissionModel);
        }

        // Log how many permissions were assigned
        \Log::info('Permissions assigned to role', [
            'role_id' => $role->id,
            'permission_count' => count($permissions)
        ]);
    }
    /**
     * Custom method to ensure proper role assignment with organization context
     */
    private function assignRoleToUser(User $user, Role $role, int $organisationId): void
    {
        // First try with the Spatie method
        try {
            $user->assignRole($role);
        } catch (\Exception $e) {
            \Log::warning('Error assigning role via Spatie method: ' . $e->getMessage());

            // Direct DB insert as fallback
            \DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'organisation_id' => $organisationId,
            ]);
        }

        // Verify the assignment worked
        $assigned = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->where('organisation_id', $organisationId)
            ->exists();

        \Log::info('Role assignment check', [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'organisation_id' => $organisationId,
            'assigned' => $assigned
        ]);
    }
}
