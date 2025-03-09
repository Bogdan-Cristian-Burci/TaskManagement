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

        DB::beginTransaction(); // Add a transaction to ensure consistency

        try{

            // Create new user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                // Add any other required fields that might be in your validation
            ]);

            // Create a unique organization name
            $baseName = $user->name . "'s Workspace";
            $organisationName = $this->generateUniqueOrganizationName($baseName);

            // Create personal organization for the user
            $organisation = Organisation::create([
                'name' => $organisationName,
                'description' => 'Personal workspace created during registration',
                'unique_id' => Str::slug($organisationName) . '-' . Str::random(6), // Add a unique identifier
                'owner_id'=> $user->id
            ]);

            // Set default organisation id
            $user->organisation_id = $organisation->id;
            $user->save();

            // Associate user with organization
            $user->organisations()->attach($organisation->id);

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

            // Assign admin permissions to the role
            $this->assignPermissionsToRole($adminRole);

            //$user->assignOrganisationRole($adminRole, $organisation);

            DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id, // Role ID for 'member'
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'organisation_id' => $organisation->id,
            ]);

            // Clear permission cache
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            // CRITICAL: Refresh user from database to get the newly assigned role
            $user = User::with(['roles', 'permissions', 'organisation'])->find($user->id);

            // Force reload of roles and permissions
            $roleNames = DB::table('roles')
                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->pluck('roles.name')
                ->toArray();

            // Create token with appropriate scopes
            $token = $user->createToken('Registration Token', ['read-user'])->accessToken;

            // Commit the transaction
            DB::commit();

            // Log the registration (optional but helpful for security audits)
            Log::info('User registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => request()->ip()
            ]);

            return response()->json([
                'message' => 'Registration successful',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'organisation_id' => $user->organisation_id,

                    // Explicitly include roles from our direct query
                    'roles' => $roleNames,

                    // Include permissions if available
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),

                    // Include organisation data
                    'organisation' => [
                        'id' => $organisation->id,
                        'name' => $organisation->name,
                        'slug' => $organisation->slug ?? Str::slug($organisation->name),
                        'unique_id' => $organisation->unique_id,
                        'description' => $organisation->description,
                        'logo' => $organisation->logo,
                        'owner_id' => $organisation->owner_id,
                        'created_at' => $organisation->created_at,
                        'updated_at' => $organisation->updated_at,
                    ],

                    // Explicitly set permissions for admin
                    'can' => [
                        'update' => true,
                        'delete' => true,
                        'manage_roles' => true,
                    ],

                    // Include links
                    'links' => [
                        'self' => route('users.show', $user->id),
                        'teams' => route('users.teams', $user->id),
                        'tasks' => route('users.tasks', $user->id),
                        'projects' => route('users.projects', $user->id),
                    ],
                ],
            ], Response::HTTP_CREATED);
        }catch (\Exception $e){
            // Roll back the transaction if something fails
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
     * Assign permissions to the admin role
     */
    private function assignPermissionsToRole(Role $role)
    {
        // Get all permissions from the database
        $allPermissions = \Spatie\Permission\Models\Permission::where('guard_name', 'api')->get();

        // Log what we're doing
        \Log::info('Assigning permissions to admin role', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'organisation_id' => $role->organisation_id,
            'permission_count' => $allPermissions->count()
        ]);

        // Assign all permissions to the role
        foreach ($allPermissions as $permission) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
