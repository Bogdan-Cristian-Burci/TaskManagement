<?php

namespace App\Http\Controllers;

use App\Models\RoleTemplate;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleTemplateController extends Controller
{
    /**
     * Display a listing of templates for the current organization
     *
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('permissions.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Get organization-specific templates and system templates
        $templates = RoleTemplate::forOrganisation($organisationId)
            ->get()
            ->map(function($template) {
                $result = $template->toArray();

                // Count roles using this template
                $result['roles_count'] = Role::where('template_id', $template->id)->count();

                return $result;
            });

        return response()->json(['templates' => $templates]);
    }

    /**
     * Store a new template
     *
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('permissions.manage', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
            'level' => 'required|integer|min:1|max:100',
        ]);

        $organisationId = $request->user()->organisation_id;

        // Check for duplicate name
        $exists = RoleTemplate::where('name', $validated['name'])
            ->where(function($query) use ($organisationId) {
                $query->where('organisation_id', $organisationId)
                    ->orWhere('is_system', true);
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A template with this name already exists'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create the template
            $template = RoleTemplate::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'level' => $validated['level'],
                'organisation_id' => $organisationId,
                'is_system' => false
            ]);

            // Create permissions if they don't exist and attach to template
            foreach ($validated['permissions'] as $permissionName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['guard_name' => 'api']
                );

                // Add to template_has_permissions
                DB::table('template_has_permissions')->insert([
                    'role_template_id' => $template->id,
                    'permission_id' => $permission->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Set permissions on template object for response
            $template->permissions = $validated['permissions'];

            DB::commit();

            return response()->json([
                'message' => 'Template created successfully',
                'template' => $template
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific template
     *
     */
    public function show(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('permissions.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Find template that's either for this org or is a system template
        $template = RoleTemplate::where('id', $id)
            ->where(function($query) use ($organisationId) {
                $query->where('organisation_id', $organisationId)
                    ->orWhere('is_system', true);
            })
            ->firstOrFail();

        // Get roles using this template
        $roles = Role::where('template_id', $template->id)
            ->where('organisation_id', $organisationId)
            ->get(['id', 'name', 'display_name', 'description']);

        return response()->json([
            'template' => $template,
            'roles' => $roles
        ]);
    }

    /**
     * Update a template
     *
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('permissions.manage', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'display_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|required|array',
            'permissions.*' => 'string',
            'level' => 'sometimes|required|integer|min:1|max:100',
        ]);

        $organisationId = $request->user()->organisation_id;

        // Find and ensure template belongs to this organization
        $template = RoleTemplate::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->firstOrFail();

        // Don't allow updating system templates
        if ($template->is_system) {
            return response()->json([
                'message' => 'Cannot modify system templates'
            ], 403);
        }

        // Check for duplicate name if name is being changed
        if (isset($validated['name']) && $validated['name'] !== $template->name) {
            $exists = RoleTemplate::where('name', $validated['name'])
                ->where(function($query) use ($organisationId) {
                    $query->where('organisation_id', $organisationId)
                        ->orWhere('is_system', true);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'A template with this name already exists'
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Update basic template properties
            $templateData = array_intersect_key($validated, [
                'name' => true,
                'display_name' => true,
                'description' => true,
                'level' => true
            ]);

            if (!empty($templateData)) {
                $template->update($templateData);
            }

            // Update permissions if provided
            if (isset($validated['permissions'])) {
                // Remove existing template permissions
                DB::table('template_has_permissions')
                    ->where('role_template_id', $template->id)
                    ->delete();

                // Add new permissions
                foreach ($validated['permissions'] as $permissionName) {
                    $permission = Permission::firstOrCreate(
                        ['name' => $permissionName],
                        ['guard_name' => 'api']
                    );

                    // Add to template_has_permissions
                    DB::table('template_has_permissions')->insert([
                        'role_template_id' => $template->id,
                        'permission_id' => $permission->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                // Set permissions on template object for response
                $template->permissions = $validated['permissions'];
            } else {
                // Load permissions for response
                $template->load('permissions');
            }

            DB::commit();

            return response()->json([
                'message' => 'Template updated successfully',
                'template' => $template
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template
     *
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('permissions.manage', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Find and ensure template belongs to this organization
        $template = RoleTemplate::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->firstOrFail();

        // Don't allow deleting system templates
        if ($template->is_system) {
            return response()->json([
                'message' => 'Cannot delete system templates'
            ], 403);
        }

        // Check if any roles are using this template
        $rolesUsingTemplate = Role::where('template_id', $template->id)->count();

        if ($rolesUsingTemplate > 0) {
            return response()->json([
                'message' => 'Cannot delete template that is in use by roles',
                'roles_count' => $rolesUsingTemplate
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Remove template permissions
            DB::table('template_has_permissions')
                ->where('role_template_id', $template->id)
                ->delete();

            // Delete the template
            $template->delete();

            DB::commit();

            return response()->json([
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
