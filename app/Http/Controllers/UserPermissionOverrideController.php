<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;

class UserPermissionOverrideController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * List user's permission overrides
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index(Request $request, int $userId): JsonResponse
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('permission.view', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to view permission overrides.');
        }

        $organisationId = $request->user()->organisation_id;

        // Verify user exists and belongs to organization
        $user = User::where('id', $userId)
            ->whereHas('organisations', function($query) use ($organisationId) {
                $query->where('organisations.id', $organisationId);
            })
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found in this organization'], 404);
        }

        // Get user's permission overrides
        $overrides = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', get_class($user))
            ->where('model_has_permissions.organisation_id', $organisationId)
            ->select(
                'permissions.id',
                'permissions.name',
                'model_has_permissions.grant',
                DB::raw('CASE WHEN model_has_permissions.grant = 1 THEN "grant" ELSE "deny" END as type')
            )
            ->get();

        // Get user's role templates in this organization
        $roles = $user->getOrganisationRoles($organisationId);

        // Format roles for response
        $roleData = $roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'template_id' => $role->template_id,
                'template_name' => $role->template->name ?? null,
                'level' => $role->level
            ];
        });

        // Get template permissions for these roles
        $templatePermissions = [];
        foreach ($roles as $role) {
            if ($role->template) {
                $templatePermissions[$role->name] = $role->template->getPermissions();
            }
        }

        // Get effective permissions after overrides
        $effectivePermissions = $user->getAllPermissions($organisationId)
            ->pluck('name')
            ->toArray();

        return response()->json([
            'user_id' => $userId,
            'user_name' => $user->name,
            'overrides' => $overrides,
            'roles' => $roleData,
            'template_permissions' => $templatePermissions,
            'effective_permissions' => $effectivePermissions
        ]);
    }

    /**
     * Add a permission override for a user
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function store(Request $request, int $userId): JsonResponse
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('permission.manage', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to manage permission overrides.');
        }

        $validated = $request->validate([
            'permission' => 'required|string|exists:permissions,name',
            'type' => 'required|in:grant,deny'
        ]);

        $organisationId = $request->user()->organisation_id;

        // Verify user exists and belongs to organization
        $user = User::where('id', $userId)
            ->whereHas('organisations', function($query) use ($organisationId) {
                $query->where('organisations.id', $organisationId);
            })
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found in this organization'], 404);
        }

        try {
            // Convert type to boolean grant value (grant = true, deny = false)
            $isGrant = $validated['type'] === 'grant';

            // Get or create permission
            $permission = DB::table('permissions')
                ->where('name', $validated['permission'])
                ->first();

            if (!$permission) {
                return response()->json(['message' => 'Invalid permission name'], 400);
            }

            // Add or update the permission override
            DB::table('model_has_permissions')->updateOrInsert([
                'permission_id' => $permission->id,
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'organisation_id' => $organisationId
            ], [
                'grant' => $isGrant,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'message' => 'Permission override added successfully',
                'details' => [
                    'user_id' => $userId,
                    'permission' => $validated['permission'],
                    'type' => $validated['type']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add permission override', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to add permission override',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a permission override for a user
     *
     * @param Request $request
     * @param int $userId
     * @param int $permissionId
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Request $request, int $userId, int $permissionId): JsonResponse
    {
        // Check permission using hasPermission directly
        if (!$request->user()->hasPermission('permission.manage', $request->user()->organisation_id)) {
            throw new AuthorizationException('You do not have permission to manage permission overrides.');
        }

        $organisationId = $request->user()->organisation_id;

        // Verify user exists and belongs to organization
        $user = User::where('id', $userId)
            ->whereHas('organisations', function($query) use ($organisationId) {
                $query->where('organisations.id', $organisationId);
            })
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found in this organization'], 404);
        }

        try {
            // Remove the permission override
            $deleted = DB::table('model_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('model_id', $userId)
                ->where('model_type', get_class($user))
                ->where('organisation_id', $organisationId)
                ->delete();

            if (!$deleted) {
                return response()->json(['message' => 'Permission override not found'], 404);
            }

            return response()->json([
                'message' => 'Permission override removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove permission override', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to remove permission override',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
