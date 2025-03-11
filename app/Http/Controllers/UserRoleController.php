<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignUserRoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserRoleController extends Controller
{
    /**
     * Assign roles to a user
     */
    public function assignRoles(AssignUserRoleRequest $request, $userId): JsonResponse
    {
        $validated = $request->validated();
        $organisation_id = $request->user()->organisation_id;

        // Find the user
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if user is in the same organization
        if ($user->organisation_id !== $organisation_id) {
            return response()->json(['message' => 'User not in your organization'], 403);
        }

        DB::beginTransaction();

        try {
            // Remove existing roles
            DB::table('model_has_roles')
                ->where('model_id', $userId)
                ->where('model_type', get_class($user))
                ->where('organisation_id', $organisation_id)
                ->delete();

            // Assign new roles
            foreach ($validated['roles'] as $roleId) {
                // Check if role belongs to organization
                $role = DB::table('roles')
                    ->where('id', $roleId)
                    ->where('organisation_id', $organisation_id)
                    ->first();

                if ($role) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $roleId,
                        'model_id' => $userId,
                        'model_type' => get_class($user),
                        'organisation_id' => $organisation_id
                    ]);
                }
            }

            // Clear permission cache
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            DB::commit();

            // Get roles of the user
            $roles = DB::table('roles')
                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $userId)
                ->where('model_has_roles.model_type', get_class($user))
                ->where('model_has_roles.organisation_id', $organisation_id)
                ->select('roles.id', 'roles.name', 'roles.level', 'roles.description')
                ->get();

            return response()->json([
                'message' => 'Roles assigned successfully',
                'user_id' => $userId,
                'roles' => $roles
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to assign roles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to assign roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get roles of a user
     */
    public function getUserRoles(Request $request, $userId): JsonResponse
    {
        if (!$request->user()->can('role.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisation_id = $request->user()->organisation_id;

        // Find the user
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if user is in the same organization
        if ($user->organisation_id !== $organisation_id) {
            return response()->json(['message' => 'User not in your organization'], 403);
        }

        // Get roles of the user
        $roles = DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', get_class($user))
            ->where('model_has_roles.organisation_id', $organisation_id)
            ->select('roles.id', 'roles.name', 'roles.level', 'roles.description')
            ->get();

        // Get all available roles in the organization for assignment
        $availableRoles = DB::table('roles')
            ->where('organisation_id', $organisation_id)
            ->select('id', 'name', 'level', 'description')
            ->get();

        return response()->json([
            'user_id' => $userId,
            'user_name' => $user->name,
            'roles' => $roles,
            'available_roles' => $availableRoles
        ]);
    }

    /**
     * Get users with specific role
     */
    public function getUsersByRole(Request $request, $roleId): JsonResponse
    {
        if (!$request->user()->can('role.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisation_id = $request->user()->organisation_id;

        // Check if role exists and belongs to the organization
        $role = DB::table('roles')
            ->where('id', $roleId)
            ->where('organisation_id', $organisation_id)
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Get users with this role
        $users = DB::table('users')
            ->join('model_has_roles', function($join) use ($roleId, $organisation_id) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->where('model_has_roles.role_id', $roleId)
                    ->where('model_has_roles.organisation_id', $organisation_id);
            })
            ->select('users.id', 'users.name', 'users.email', 'users.avatar')
            ->get();

        return response()->json([
            'role_id' => $roleId,
            'role_name' => $role->name,
            'users' => $users
        ]);
    }
}
