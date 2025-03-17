<?php

namespace App\Http\Controllers;


use App\Models\RoleTemplate;
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
        if (!$request->user()->canWithOrg('role.create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validated();
        $organisation_id = $request->user()->organisation_id;

        DB::beginTransaction();

        try {

            // Check if template_id is provided and valid
            $templateId = null;
            if (isset($validated['template_id'])) {
                $template = RoleTemplate::where('id', $validated['template_id'])
                    ->where('organisation_id', $organisationId)
                    ->first();

                if (!$template) {
                    return response()->json(['message' => 'Invalid template'], 422);
                }

                $templateId = $template->id;
            }

            // Create the role
            $roleId = DB::table('roles')->insertGetId([
                'name' => $validated['name'],
                'guard_name' => 'api',
                'organisation_id' => $organisation_id,
                'level' => $validated['level'] ?? 10, // Default level if not provided
                'template_id' => $templateId,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            $role = DB::table('roles')->where('id', $roleId)->first();

            // Get template information
            if ($templateId) {
                $template = RoleTemplate::find($templateId);
                $role->template = $template;
                $role->permissions = $template->permissions;
            }

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create role', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create role'], 500);
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

        // Get template information if available
        if ($role->template_id) {
            $template = RoleTemplate::find($role->template_id);
            $role->template = $template;
            $role->permissions = $template ? $template->permissions : [];
        } else {
            // For legacy roles, get permissions from role_has_permissions
            $permissions = DB::table('permissions')
                ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $id)
                ->pluck('permissions.name')
                ->toArray();

            $role->permissions = $permissions;
        }

        // Get users with this role
        $users = DB::table('users')
            ->join('model_has_roles', function($join) use ($id, $organisationId) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->where('model_has_roles.role_id', $id)
                    ->where('model_has_roles.organisation_id', $organisationId);
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
        $organisationId = $request->user()->organisation_id;

        // Find the role
        $role = DB::table('roles')
            ->where('id', $id)
            ->where('organisation_id', $organisationId)
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

            // Handle template_id updates
            if (isset($validated['template_id'])) {
                if ($validated['template_id'] === null) {
                    // Remove template association
                    $updateData['template_id'] = null;
                } else {
                    // Verify template exists and belongs to this org
                    $template = RoleTemplate::where('id', $validated['template_id'])
                        ->where('organisation_id', $organisationId)
                        ->first();

                    if (!$template) {
                        return response()->json(['message' => 'Invalid template'], 422);
                    }

                    $updateData['template_id'] = $template->id;
                }
            }

            if (!empty($updateData)) {
                $updateData['updated_at'] = now();

                // Update the role
                DB::table('roles')
                    ->where('id', $id)
                    ->update($updateData);
            }

            DB::commit();

            // Get updated role
            $updatedRole = DB::table('roles')->where('id', $id)->first();

            // Get template information if available
            if ($updatedRole->template_id) {
                $template = RoleTemplate::find($updatedRole->template_id);
                $updatedRole->template = $template;
                $updatedRole->permissions = $template ? $template->permissions : [];
            } else {
                // For legacy roles, get permissions from role_has_permissions
                $permissions = DB::table('permissions')
                    ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where('role_has_permissions.role_id', $id)
                    ->pluck('permissions.name')
                    ->toArray();

                $updatedRole->permissions = $permissions;
            }

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
