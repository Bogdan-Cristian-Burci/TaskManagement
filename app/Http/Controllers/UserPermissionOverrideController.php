<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserPermissionOverrideController extends Controller
{
    /**
     * List user's permission overrides
     */
    public function index(Request $request, $userId): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Verify user exists and belongs to organization
        $user = User::where('id', $userId)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found in this organization'], 404);
        }

        // Get user's permission overrides
        $overrides = DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', get_class($user))
            ->where('model_has_permissions.organisation_id', $organisationId)
            ->select('permissions.id', 'permissions.name', 'model_has_permissions.type')
            ->get();

        // Get user's role(s)
        $roles = DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', get_class($user))
            ->where('model_has_roles.organisation_id', $organisationId)
            ->select('roles.id', 'roles.name', 'roles.template_id')
            ->get();

        // Get template permissions for these roles
        $templatePermissions = [];
        foreach ($roles as $role) {
            if ($role->template_id) {
                $template = DB::table('role_templates')
                    ->where('id', $role->template_id)
                    ->first();

                if ($template) {
                    $templatePermissions[$role->name] = json_decode($template->permissions, true);
                }
            }
        }

        return response()->json([
            'user_id' => $userId,
            'user_name' => $user->name,
            'overrides' => $overrides,
            'roles' => $roles,
            'template_permissions' => $templatePermissions
        ]);
    }

    /**
     * Add a permission override for a user
     */
    public function store(Request $request, $userId): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'permission' => 'required|string',
            'type' => 'required|in:grant,deny'
        ]);

        $organisationId = $request->user()->organisation_id;

        // Verify user exists and belongs to organization
        $user = User::where('id', $userId)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found in this organization'], 404);
        }

        try {
            // Add the permission override
            $user->addPermissionOverride(
                $validated['permission'],
                $organisationId,
                $validated['type']
            );

            return response()->json([
                'message' => 'Permission override added successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add permission override', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to add permission override',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a permission override for a user
     */
    public function destroy(Request $request, $userId, $permissionId): JsonResponse
    {
        if (!$request->user()->canWithOrg('permission.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Verify user exists and belongs to organization
        $user = User::where('id', $userId)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found in this organization'], 404);
        }

        // Get the permission name
        $permission = DB::table('permissions')
            ->where('id', $permissionId)
            ->value('name');

        if (!$permission) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        try {
            // Remove the permission override
            $user->removePermissionOverride($permission, $organisationId);

            return response()->json([
                'message' => 'Permission override removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove permission override', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to remove permission override',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
