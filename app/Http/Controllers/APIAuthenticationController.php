<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\LoginUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Organisation;
use App\Models\RoleTemplate;
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

            // Set organization as user's primary organization
            $user->update(['organisation_id' => $organisation->id]);

            // Associate user with organization
            $user->organisations()->attach($organisation->id);

            // Create admin template if it doesn't exist
            $this->assignAdminRoleFromTemplate($user, $organisation->id);

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

    // When creating an admin role for new organization:

    /**
     * Assign admin role to a user using the standard admin template
     *
     * @param User $user
     * @param int $organisationId
     * @throws \Exception
     */
    private function assignAdminRoleFromTemplate(User $user, int $organisationId): void
    {
        // Find the Demo Organization
        $demoOrg = Organisation::where('name', 'Demo Organization')->first();

        if (!$demoOrg) {
            Log::error("Demo Organization not found. Please run the seeders first.");
            throw new \Exception("Demo Organization not found. System is not properly initialized.");
        }

        // Find the admin role template from Demo Org
        $adminRoleTemplate = DB::table('roles')
            ->join('role_templates', 'roles.template_id', '=', 'role_templates.id')
            ->where('roles.name', 'admin')
            ->where('roles.organisation_id', $demoOrg->id)
            ->select('role_templates.id as template_id', 'roles.level')
            ->first();

        if (!$adminRoleTemplate) {
            Log::error("Admin template not found in Demo Organization. Please run the seeders first.");
            throw new \Exception("Admin template not found. System is not properly initialized.");
        }

        // Create admin role with template reference
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'api',
            'organisation_id' => $organisationId,
            'level' => $adminRoleTemplate->level,
            'template_id' => $adminRoleTemplate->template_id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Assign role to user
        DB::table('model_has_roles')->insert([
            'role_id' => $adminRoleId,
            'model_id' => $user->id,
            'model_type' => 'App\\Models\\User',
            'organisation_id' => $organisationId
        ]);

        Log::info("Admin role assigned to user using template", [
            'user_id' => $user->id,
            'organisation_id' => $organisationId,
            'template_id' => $adminRoleTemplate->template_id
        ]);
    }
}
