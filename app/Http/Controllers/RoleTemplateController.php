<?php

namespace App\Http\Controllers;

use App\Models\RoleTemplate;
use App\Models\Role;
use App\Models\Permission;
use App\Services\RoleTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleTemplateController extends Controller
{
    protected RoleTemplateService $templateService;

    /**
     * Constructor to inject dependencies
     */
    public function __construct(RoleTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Display a listing of templates for the current organization
     * including system templates
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('role.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Get names of system templates that have been overridden
        $overriddenTemplateNames = RoleTemplate::where('organisation_id', $organisationId)
            ->pluck('name')
            ->toArray();

        // Get templates that are either:
        // 1. Organization-specific templates, OR
        // 2. System templates that haven't been overridden
        $templates = RoleTemplate::where(function($query) use ($organisationId) {
            $query->where('organisation_id', $organisationId);
        })
            ->orWhere(function($query) use ($overriddenTemplateNames) {
                $query->where('is_system', true)
                    ->whereNull('organisation_id')
                    ->whereNotIn('name', $overriddenTemplateNames);
            })
            ->with('permissions')
            ->get()
            ->map(function($template) {
                $result = $template->toArray();

                // Count roles using this template
                $result['roles_count'] = Role::where('template_id', $template->id)->count();

                // Check if this template is a system template
                $result['is_system'] = (bool)$template->is_system;

                // Indicate if this is an organization-specific template
                $result['is_organization_specific'] = !is_null($template->organisation_id);

                // Format permissions as a simple array of permission names for easier frontend use
                $result['permission_names'] = collect($result['permissions'])->pluck('name')->toArray();

                return $result;
            });

        return response()->json(['templates' => $templates]);
    }

    /**
     * Store a new template using RoleTemplateService
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('role.create', $request->user()->organisation_id)) {
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

        // Prepare template data
        $templateData = [
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'level' => $validated['level'],
            'organisation_id' => $organisationId,
            'is_system' => false,
            'can_be_deleted' => true,
            'scope' => 'organization'
        ];

        DB::beginTransaction();

        try {
            // Get or create permissions and collect their IDs
            $permissionIds = [];
            foreach ($validated['permissions'] as $permissionName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['guard_name' => 'api']
                );
                $permissionIds[] = $permission->id;
            }

            // Create template using the service
            $template = $this->templateService->createTemplate($templateData, $permissionIds);

            // Load relationships for response
            $template->load('permissions');

            // Format response data
            $responseData = $template->toArray();
            $responseData['permission_names'] = collect($template->permissions)->pluck('name')->toArray();

            DB::commit();

            return response()->json([
                'message' => 'Template created successfully',
                'template' => $responseData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific template
     */
    public function show(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('role.view', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisationId = $request->user()->organisation_id;

        // Find template that's either for this org or is a system template
        $template = RoleTemplate::where('id', $id)
            ->where(function($query) use ($organisationId) {
                $query->where('organisation_id', $organisationId)
                    ->orWhere(function($q) {
                        $q->where('is_system', true)
                            ->whereNull('organisation_id');
                    });
            })
            ->with('permissions')
            ->firstOrFail();

        // Format template for response
        $templateData = $template->toArray();
        $templateData['permission_names'] = collect($templateData['permissions'])->pluck('name')->toArray();

        // Get roles using this template
        $roles = Role::where('template_id', $template->id)
            ->where(function($query) use ($organisationId) {
                $query->where('organisation_id', $organisationId)
                    ->orWhereNull('organisation_id');
            })
            ->get(['id', 'organisation_id', 'template_id'])
            ->map(function($role) {
                // Add derived properties from the template
                return [
                    'id' => $role->id,
                    'organisation_id' => $role->organisation_id,
                    'template_id' => $role->template_id,
                    'name' => $role->getName(),
                    'display_name' => $role->getDisplayName(),
                    'description' => $role->getDescription(),
                ];
            });

        // Check if this template has a system counterpart or is itself a system template
        $hasSystemCounterpart = false;
        $systemTemplate = null;

        if (!$template->is_system && $template->organisation_id === $organisationId) {
            $systemTemplate = RoleTemplate::where('name', $template->name)
                ->where('is_system', true)
                ->whereNull('organisation_id')
                ->first();

            if ($systemTemplate) {
                $hasSystemCounterpart = true;
                $templateData['system_template_id'] = $systemTemplate->id;
            }
        }

        $templateData['has_system_counterpart'] = $hasSystemCounterpart;

        return response()->json([
            'template' => $templateData,
            'roles' => $roles
        ]);
    }

    /**
     * Update a template using RoleTemplateService
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('role.update', $request->user()->organisation_id)) {
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
            // Prepare data for updating
            $templateData = array_intersect_key($validated, [
                'name' => true,
                'display_name' => true,
                'description' => true,
                'level' => true
            ]);

            // Prepare permission IDs if permissions are provided
            $permissionIds = null;
            if (isset($validated['permissions'])) {
                $permissionIds = [];
                foreach ($validated['permissions'] as $permissionName) {
                    $permission = Permission::firstOrCreate(
                        ['name' => $permissionName],
                        ['guard_name' => 'api']
                    );
                    $permissionIds[] = $permission->id;
                }
            }

            // Update the template using the service
            $updatedTemplate = $this->templateService->updateTemplate($template, $templateData, $permissionIds);

            // Load permissions for response
            $updatedTemplate->load('permissions');

            // Format for response
            $responseData = $updatedTemplate->toArray();
            $responseData['permission_names'] = collect($updatedTemplate->permissions)->pluck('name')->toArray();

            DB::commit();

            return response()->json([
                'message' => 'Template updated successfully',
                'template' => $responseData
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template using RoleTemplateService
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasPermission('role.delete', $request->user()->organisation_id)) {
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
            // Use the service to delete the template
            $result = $this->templateService->deleteTemplate($template);

            if (!$result) {
                throw new \Exception("Failed to delete template");
            }

            DB::commit();

            return response()->json(['message' => 'Template deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add permissions to a template
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

        // Find and ensure template belongs to this organization
        $template = RoleTemplate::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Don't allow updating system templates
        if ($template->is_system) {
            return response()->json([
                'message' => 'Cannot modify system templates'
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Get or create permissions and collect their IDs
            $permissionIds = [];
            foreach ($validated['permissions'] as $permissionName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['guard_name' => 'api']
                );
                $permissionIds[] = $permission->id;
            }

            // Use the service method instead of local helper method
            $updatedTemplate = $this->templateService->addPermissionsToTemplate($template, $permissionIds);

            // Load permissions for response
            $updatedTemplate->load('permissions');

            // Format for response
            $responseData = $updatedTemplate->toArray();
            $responseData['permission_names'] = collect($updatedTemplate->permissions)->pluck('name')->toArray();

            DB::commit();

            return response()->json([
                'message' => 'Permissions added successfully',
                'template' => $responseData
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add permissions to template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to add permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove permissions from a template
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

        // Find and ensure template belongs to this organization
        $template = RoleTemplate::where('id', $id)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Don't allow updating system templates
        if ($template->is_system) {
            return response()->json([
                'message' => 'Cannot modify system templates'
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Get permission IDs from names
            $permissionIds = Permission::whereIn('name', $validated['permissions'])
                ->pluck('id')
                ->toArray();

            // Use the service method instead of local helper method
            $updatedTemplate = $this->templateService->removePermissionsFromTemplate($template, $permissionIds);

            // Load permissions for response
            $updatedTemplate->load('permissions');

            // Format for response
            $responseData = $updatedTemplate->toArray();
            $responseData['permission_names'] = collect($updatedTemplate->permissions)->pluck('name')->toArray();

            DB::commit();

            return response()->json([
                'message' => 'Permissions removed successfully',
                'template' => $responseData
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove permissions from template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to remove permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to add permissions to a template without removing existing ones
     *
     * @param RoleTemplate $template
     * @param array $permissionIds
     * @return RoleTemplate
     */
    protected function addPermissionsToTemplate(RoleTemplate $template, array $permissionIds)
    {
        if (!empty($permissionIds)) {
            // Get current permissions to avoid duplicates
            $existingPermissionIds = $template->permissions()->pluck('id')->toArray();

            // Filter out permissions that already exist
            $newPermissionIds = array_diff($permissionIds, $existingPermissionIds);

            // Attach only new permissions
            if (!empty($newPermissionIds)) {
                $template->permissions()->attach($newPermissionIds);
            }
        }

        return $template->fresh();
    }

    /**
     * Helper method to remove specific permissions from a template
     *
     * @param RoleTemplate $template
     * @param array $permissionIds
     * @return RoleTemplate
     */
    protected function removePermissionsFromTemplate(RoleTemplate $template, array $permissionIds)
    {
        if (!empty($permissionIds)) {
            $template->permissions()->detach($permissionIds);
        }

        return $template->fresh();
    }

    /**
     * Get system templates available for override
     */
    public function getSystemTemplates(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage-roles', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get system templates
        $systemTemplates = RoleTemplate::where('is_system', true)
            ->whereNull('organisation_id')
            ->with('permissions')
            ->get()
            ->map(function($template) {
                $result = $template->toArray();
                $result['permission_names'] = collect($result['permissions'])->pluck('name')->toArray();
                return $result;
            });

        return response()->json([
            'system_templates' => $systemTemplates
        ]);
    }

    /**
     * Create an organization-specific override of a system template
     */
    public function overrideSystemTemplate(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermission('manage-permissions', $request->user()->organisation_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'system_template_id' => 'required|exists:role_templates,id',
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string',
        ]);

        $organisationId = $request->user()->organisation_id;

        // Find the system template
        $systemTemplate = RoleTemplate::where('id', $validated['system_template_id'])
            ->where('is_system', true)
            ->whereNull('organisation_id')
            ->with('permissions')
            ->first();

        if (!$systemTemplate) {
            return response()->json([
                'message' => 'Invalid system template'
            ], 422);
        }

        // Check if an override already exists
        $existingOverride = RoleTemplate::where('name', $systemTemplate->name)
            ->where('organisation_id', $organisationId)
            ->first();

        if ($existingOverride) {
            return response()->json([
                'message' => 'An override for this template already exists',
                'existing_template' => $existingOverride
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create template data for the override
            $templateData = [
                'name' => $systemTemplate->name,
                'display_name' => $validated['display_name'] ?? $systemTemplate->display_name,
                'description' => $validated['description'] ?? $systemTemplate->description,
                'level' => $systemTemplate->level,
                'organisation_id' => $organisationId,
                'is_system' => false,
                'can_be_deleted' => true,
                'scope' => 'organization'
            ];

            // Get or create permissions and collect their IDs
            // Use either provided permissions or copy from system template
            $permissionNames = isset($validated['permissions'])
                ? $validated['permissions']
                : $systemTemplate->permissions->pluck('name')->toArray();

            $permissionIds = [];
            foreach ($permissionNames as $permissionName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['guard_name' => 'api']
                );
                $permissionIds[] = $permission->id;
            }

            // Create the template using service
            $override = $this->templateService->createTemplate($templateData, $permissionIds);

            // Migrate existing users to the new template
            $migrationResult = $this->templateService->migrateUsersToTemplateOverride(
                $systemTemplate,
                $override,
                $organisationId
            );

            // Load permissions for response
            $override->load('permissions');

            // Format for response
            $overrideData = $override->toArray();
            $overrideData['permission_names'] = collect($overrideData['permissions'])->pluck('name')->toArray();
            $overrideData['overrides_system_template'] = $systemTemplate->id;
            $overrideData['users_migrated'] = $migrationResult['migrated'];
            if (isset($migrationResult['error'])) {
                $overrideData['migration_error'] = $migrationResult['error'];
            }

            DB::commit();

            return response()->json([
                'message' => 'System template overridden successfully',
                'template' => $overrideData
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to override system template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to override system template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
