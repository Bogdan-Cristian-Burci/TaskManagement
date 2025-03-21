<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\Role;
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
     *
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('role.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Get all roles for the organization with templates
        $roles = Role::where('organisation_id', $organisationId)
            ->with('template')
            ->get()
            ->map(function($role) {
                $result = $role->toArray();

                // Get permissions through template
                if ($role->template) {
                    $result['permissions'] = $role->template->permissions;
                } else {
                    // Legacy fallback - should not be needed with new system
                    $result['permissions'] = [];
                }

                // Count users with this role
                $result['users_count'] = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->where('organisation_id', $role->organisation_id)
                    ->count();

                return $result;
            });

        return response()->json([
            'roles' => $roles
        ]);
    }

    /**
     * Store a newly created role
     *
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {

        $validated = $request->validated();
        $organisationId = $request->user()->organisation_id;

        DB::beginTransaction();

        try {
            // Check if template_id is provided and valid
            $template = null;
            $templateId = null;

            if (isset($validated['template_id'])) {
                $template = RoleTemplate::find($validated['template_id']);

                // Validate template belongs to organization or is system template
                if (!$template ||
                    ($template->organisation_id !== $organisationId && !$template->is_system)) {
                    return response()->json(['message' => 'Invalid template'], 422);
                }

                $templateId = $template->id;
            }

            // Create the role
            $role = Role::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'] ?? $validated['name'],
                'description' => $validated['description'] ?? null,
                'organisation_id' => $organisationId,
                'template_id' => $templateId,
            ]);

            DB::commit();

            // Load template relationship
            if ($template) {
                $role->load('template');
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
     *
     */
    public function show(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.view', $request->user()->organisation_id)) {
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

        $result = $role->toArray();

        // Get permissions through template
        if ($role->template) {
            $result['permissions'] = $role->template->permissions;
        } else {
            $result['permissions'] = [];
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

        $result['users'] = $users;

        return response()->json(['role' => $result]);
    }

    /**
     * Update the specified role
     *
     */
    public function update(UpdateRoleRequest $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.update', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();
        $organisationId = $request->user()->organisation_id;

        // Find the role
        $role = Role::where('id', $id)
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

            if (isset($validated['display_name'])) {
                $updateData['display_name'] = $validated['display_name'];
            }

            if (isset($validated['description'])) {
                $updateData['description'] = $validated['description'];
            }

            // Handle template_id updates
            if (isset($validated['template_id'])) {
                if ($validated['template_id'] === null) {
                    // Remove template association
                    $updateData['template_id'] = null;
                } else {
                    // Verify template exists and belongs to this org or is system template
                    $template = RoleTemplate::find($validated['template_id']);

                    if (!$template ||
                        ($template->organisation_id !== $organisationId && !$template->is_system)) {
                        return response()->json(['message' => 'Invalid template'], 422);
                    }

                    $updateData['template_id'] = $template->id;
                }
            }

            if (!empty($updateData)) {
                $role->update($updateData);
            }

            DB::commit();

            // Reload the role with template
            $role->refresh();
            $role->load('template');

            $result = $role->toArray();

            // Get permissions through template
            if ($role->template) {
                $result['permissions'] = $role->template->permissions;
            } else {
                $result['permissions'] = [];
            }

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => $result
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
     *
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('roles.delete', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Find the role
        $role = Role::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        // Don't allow deleting roles with admin template
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
            // Delete the role
            $role->delete();

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
}
