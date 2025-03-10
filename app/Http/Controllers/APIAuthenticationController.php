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

            // Create admin role directly
            $adminRoleId = DB::table('roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'api',
                'organisation_id' => $organisation->id,
                'level' => 80,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Assign permissions to role
            $this->assignPermissionsToRole($adminRoleId);

            // Assign role directly
            DB::table('model_has_roles')->insert([
                'role_id' => $adminRoleId,
                'model_id' => $user->id,
                'model_type' => 'App\\Models\\User',
                'organisation_id' => $organisation->id
            ]);

            // Clear permission cache
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            // Create token
            $token = $user->createToken('Registration Token', ['read-user'])->accessToken;

            // Get a fresh instance of the user to ensure we have correct data
            $freshUser = User::find($user->id);

            DB::commit();

            return response()->json([
                'message' => 'Registration successful',
                'token' => $token,
                'user' => new UserResource($freshUser),
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
     * @param int $roleId
     */
    private function assignPermissionsToRole($roleId)
    {
        // List of all permissions for admin
        $permissions = [
            // User permissions
            'user.viewAny', 'user.view', 'user.create', 'user.update', 'user.delete', 'user.forceDelete', 'user.restore',

            // Organisation permissions
            'organisation.viewAny', 'organisation.view', 'organisation.create', 'organisation.update', 'organisation.delete', 'organisation.forceDelete', 'organisation.restore',
            'organisation.inviteUser', 'organisation.removeUser', 'organisation.assignRole', 'organisation.viewMetrics', 'organisation.manageSettings', 'organisation.exportData',

            // Project permissions
            'project.viewAny', 'project.view', 'project.create', 'project.update', 'project.delete', 'project.forceDelete', 'project.restore',
            'project.addMember', 'project.removeMember', 'project.changeOwner',

            // Team permissions
            'team.view', 'team.create', 'team.update', 'team.delete',

            // Task permissions
            'task.viewAny', 'task.view', 'task.create', 'task.update', 'task.delete', 'task.forceDelete', 'task.restore',
            'task.assign', 'task.changeStatus', 'task.changePriority', 'task.addLabel', 'task.removeLabel', 'task.moveTask', 'task.attachFile', 'task.detachFile',

            // Board permissions
            'board.viewAny', 'board.view', 'board.create', 'board.update', 'board.delete', 'board.forceDelete', 'board.restore',
            'board.reorderColumns', 'board.addColumn', 'board.changeColumSettings',

            // Status permissions
            'status.viewAny', 'status.view', 'status.create', 'status.update', 'status.delete', 'status.forceDelete', 'status.restore',

            // Priority permissions
            'priority.viewAny', 'priority.view', 'priority.create', 'priority.update', 'priority.delete', 'priority.forceDelete', 'priority.restore',

            // Task Type permissions
            'taskType.viewAny', 'taskType.view', 'taskType.create', 'taskType.update', 'taskType.delete', 'taskType.forceDelete', 'taskType.restore',

            // Comment permissions
            'comment.viewAny', 'comment.view', 'comment.create', 'comment.update', 'comment.delete', 'comment.forceDelete', 'comment.restore',

            // Attachment permissions
            'attachment.viewAny', 'attachment.view', 'attachment.create', 'attachment.update', 'attachment.delete', 'attachment.forceDelete', 'attachment.restore',

            // Notification permissions
            'notification.viewAny', 'notification.view', 'notification.create', 'notification.update', 'notification.delete', 'notification.forceDelete', 'notification.restore',

            // Role and permission management
            'role.view', 'role.create', 'role.update', 'role.delete',
            'permission.view', 'permission.assign',
        ];

        // Create permissions and assign them to the role
        foreach ($permissions as $permissionName) {
            // First check if permission exists
            $permission = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', 'api')
                ->first();

            if (!$permission) {
                // Create the permission
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permissionName,
                    'guard_name' => 'api',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                $permissionId = $permission->id;
            }

            // Check if the role already has this permission
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                // Assign it to the role
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId
                ]);
            }
        }
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
