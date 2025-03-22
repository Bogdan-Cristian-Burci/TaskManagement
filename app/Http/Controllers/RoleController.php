<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use App\Services\RoleTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    protected RoleService $roleService;

    /**
     * Constructor to inject dependencies
     */
    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of roles in the organization
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('role.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Get all available roles for this organization
        $roles = $this->roleService->getAvailableRoles($organisationId)
            ->map(function($role) {
                return $this->formatRoleResponse($role);
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
        $validated = $request->validated();
        $organisationId = $request->user()->organisation_id;

        DB::beginTransaction();

        try {
            $role = null;

            // SCENARIO 1: Override existing system template
            if (isset($validated['system_template_id'])) {
                $result = $this->roleService->createSystemRoleOverride(
                    $validated['system_template_id'],
                    $organisationId,
                    [
                        'display_name' => $validated['display_name'] ?? null,
                        'description' => $validated['description'] ?? null,
                    ],
                    $validated['permissions'] ?? null
                );

                $role = $result['role'];
                $migratedUsers = $result['migrated_users'];
            }
            // SCENARIO 2: Create brand new role with custom template
            else {
                // Validate required fields for new template
                if (empty($validated['name']) || empty($validated['permissions'])) {
                    return response()->json([
                        'message' => 'Name and permissions are required when creating a custom role'
                    ], 422);
                }

                $role = $this->roleService->createCustomRole(
                    $validated,
                    $organisationId,
                    $validated['permissions']
                );
            }

            DB::commit();

            // Load template relationship for response
            $role->load('template.permissions');

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $this->formatRoleResponse($role),
                'migrated_users' => $migratedUsers ?? 0
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create role', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified role
     */
    public function show(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('role.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        $role = Role::where(function($query) use ($id, $organisationId) {
            // Either an org-specific role
            $query->where('id', $id)
                ->where('organisation_id', $organisationId);
        })
            ->orWhere(function($query) use ($id, $organisationId) {
                // Or a system role not overridden in this org
                $query->where('id', $id)
                    ->whereNull('organisation_id')
                    ->whereNotExists(function($q) use ($id, $organisationId) {
                        $q->select(DB::raw(1))
                            ->from('roles')
                            ->where('system_role_id', $id)
                            ->where('organisation_id', $organisationId);
                    });
            })
            ->with('template.permissions')
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Get users with this role
        $users = DB::table('users')
            ->join('model_has_roles', function($join) use ($role, $organisationId) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->where('model_has_roles.role_id', $role->id)
                    ->where('model_has_roles.organisation_id', $organisationId);
            })
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        $result = $this->formatRoleResponse($role);
        $result['users'] = $users;

        return response()->json(['role' => $result]);
    }

    /**
     * Update the specified role
     */
    public function update(UpdateRoleRequest $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('role.update', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();
        $organisationId = $request->user()->organisation_id;

        DB::beginTransaction();

        try {
            $role = $this->roleService->updateRole(
                $id,
                $organisationId,
                $validated,
                $validated['permissions'] ?? null
            );

            DB::commit();

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => $this->formatRoleResponse($role)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update role', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified role
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('role.delete', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        $role = Role::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->with('template')
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Don't allow deleting roles that override admin template
        if ($role->template && $role->template->name === 'admin') {
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
            // Get the template for potential deletion
            $template = $role->template;

            // Delete the role
            $role->delete();

            // If this is a custom template and no other roles use it, delete it
            if ($template &&
                !$template->is_system &&
                $template->organisation_id === $organisationId) {

                $otherRolesCount = Role::where('template_id', $template->id)
                    ->count();

                if ($otherRolesCount === 0) {
                    app(RoleTemplateService::class)->deleteTemplate($template);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Role deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete role', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add permissions to a role
     */
    public function addPermissions(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('permission.assign', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $organisationId = $request->user()->organisation_id;

        DB::beginTransaction();

        try {
            $role = $this->roleService->addPermissionsToRole(
                $id,
                $organisationId,
                $validated['permissions']
            );

            DB::commit();

            return response()->json([
                'message' => 'Permissions added successfully',
                'role' => $this->formatRoleResponse($role)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add permissions', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to add permissions', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove permissions from a role
     */
    public function removePermissions(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('permission.revoke', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $organisationId = $request->user()->organisation_id;

        DB::beginTransaction();

        try {
            $role = $this->roleService->removePermissionsFromRole(
                $id,
                $organisationId,
                $validated['permissions']
            );

            DB::commit();

            return response()->json([
                'message' => 'Permissions removed successfully',
                'role' => $this->formatRoleResponse($role)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove permissions', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to remove permissions', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Revert a role to its system template
     */
    public function revertToSystem(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('role.update', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        DB::beginTransaction();

        try {
            $result = $this->roleService->revertRoleToSystem($id, $organisationId);

            DB::commit();

            return response()->json([
                'message' => 'Role reverted to system template successfully',
                'migrated_users' => $result['migrated'] ?? 0
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revert role', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to revert role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Format role response
     */
    private function formatRoleResponse(Role $role): array
    {
        $base = [
            'id' => $role->id,
            'organisation_id' => $role->organisation_id,
            'template_id' => $role->template_id,
            'overrides_system' => $role->overrides_system,
            'system_role_id' => $role->system_role_id,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'name' => $role->getName(),
            'display_name' => $role->getDisplayName(),
            'description' => $role->getDescription(),
            'level' => $role->getLevel(),
            'permissions' => $role->getPermissions(),
            'users_count' => DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\\Models\\User')
                ->count(),
            'is_system_role' => $role->organisation_id === null
        ];

        // For system roles, check if they've been overridden
        if ($role->organisation_id === null) {
            $base['has_override'] = Role::where('system_role_id', $role->id)
                ->exists();
        }

        return $base;
    }
}
