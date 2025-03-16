<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * Display a listing of roles in the organization
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->canWithOrg('role.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisation_id = $request->user()->organisation_id;

        $roles = DB::table('roles')
            ->where('organisation_id', $organisation_id)
            ->get()
            ->map(function($role) {
                // Get permissions for each role
                $permissions = DB::table('permissions')
                    ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where('role_has_permissions.role_id', $role->id)
                    ->pluck('permissions.name')
                    ->toArray();

                $role->permissions = $permissions;

                // Count users with this role
                $usersCount = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('organisation_id', $role->organisation_id)
                    ->count();

                $role->users_count = $usersCount;

                return $role;
            });

        return response()->json([
            'roles' => $roles
        ]);
    }

    /**
     * Store a newly created role
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        if (!$request->user()->canWithOrg('manage roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validated();
        $organisation_id = $request->user()->organisation_id;

        DB::beginTransaction();

        try {
            // Create the role
            $roleId = DB::table('roles')->insertGetId([
                'name' => $validated['name'],
                'guard_name' => 'api',
                'organisation_id' => $organisation_id,
                'level' => $validated['level'] ?? 10, // Default level if not provided
                'description' => $validated['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Assign permissions if provided
            if (isset($validated['permissions']) && is_array($validated['permissions'])) {
                $this->assignPermissionsToRole($roleId, $validated['permissions']);
            }

            DB::commit();

            $role = DB::table('roles')->where('id', $roleId)->first();

            // Get permissions for this role
            $permissions = DB::table('permissions')
                ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $roleId)
                ->pluck('permissions.name')
                ->toArray();

            $role->permissions = $permissions;

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create role', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified role
     */
    public function show(Request $request, $id): JsonResponse
    {
        if (!$request->user()->canWithOrg('role.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisation_id = $request->user()->organisation_id;

        $role = DB::table('roles')
            ->where('id', $id)
            ->where('organisation_id', $organisation_id)
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Get permissions for this role
        $permissions = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $id)
            ->pluck('permissions.name')
            ->toArray();

        $role->permissions = $permissions;

        // Get users with this role
        $users = DB::table('users')
            ->join('model_has_roles', function($join) use ($id, $organisation_id) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->where('model_has_roles.role_id', $id)
                    ->where('model_has_roles.organisation_id', $organisation_id);
            })
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        $role->users = $users;

        return response()->json(['role' => $role]);
    }

    /**
     * Update the specified role
     */
    public function update(UpdateRoleRequest $request, $id): JsonResponse
    {
        if (!$request->user()->canWithOrg('role.update')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();
        $organisation_id = $request->user()->organisation_id;

        // Find the role
        $role = DB::table('roles')
            ->where('id', $id)
            ->where('organisation_id', $organisation_id)
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        DB::beginTransaction();

        try {
            $updateData = [];

            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }

            if (isset($validated['level'])) {
                $updateData['level'] = $validated['level'];
            }

            if (array_key_exists('description', $validated)) {
                $updateData['description'] = $validated['description'];
            }

            if (!empty($updateData)) {
                $updateData['updated_at'] = now();

                // Update the role
                DB::table('roles')
                    ->where('id', $id)
                    ->update($updateData);
            }

            // Update permissions if provided
            if (isset($validated['permissions'])) {
                // Remove existing permissions
                DB::table('role_has_permissions')
                    ->where('role_id', $id)
                    ->delete();

                // Assign new permissions if not empty
                if (!empty($validated['permissions'])) {
                    $this->assignPermissionsToRole($id, $validated['permissions']);
                }
            }

            DB::commit();

            // Get updated role
            $updatedRole = DB::table('roles')->where('id', $id)->first();

            // Get permissions for this role
            $permissions = DB::table('permissions')
                ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $id)
                ->pluck('permissions.name')
                ->toArray();

            $updatedRole->permissions = $permissions;

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => $updatedRole
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update role', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified role
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->canWithOrg('role.delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisation_id = $request->user()->organisation_id;

        // Find the role
        $role = DB::table('roles')
            ->where('id', $id)
            ->where('organisation_id', $organisation_id)
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Don't allow deleting the admin role
        if ($role->name === 'admin') {
            return response()->json(['message' => 'Cannot delete the admin role'], 403);
        }

        // Check if users are assigned to this role
        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $id)
            ->count();

        if ($usersCount > 0) {
            return response()->json([
                'message' => 'Cannot delete role with assigned users',
                'users_count' => $usersCount
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Remove role permissions
            DB::table('role_has_permissions')
                ->where('role_id', $id)
                ->delete();

            // Delete the role
            DB::table('roles')
                ->where('id', $id)
                ->delete();

            DB::commit();

            return response()->json(['message' => 'Role deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete role', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign permissions to a role
     *
     * @param int $roleId
     * @param array $permissions
     * @throws \Exception
     */
    private function assignPermissionsToRole(int $roleId, array $permissions)
    {
        // Verify role belongs to the current organization
        $role = DB::table('roles')->where('id', $roleId)->first();
        if (!$role || $role->organisation_id !== auth()->user()->organisation_id) {
            throw new \Exception('Invalid role for this organization');
        }

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
}
