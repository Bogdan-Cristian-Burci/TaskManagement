<?php

namespace App\Services;

use App\Models\Role;
use App\Models\RoleTemplate;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleService
{
    protected RoleTemplateService $templateService;

    public function __construct(RoleTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Create a new custom role
     *
     * @param array $data Role data
     * @param int $organisationId Organization ID
     * @param array $permissions Array of permission names
     * @return Role
     * @throws \Exception
     */
    public function createCustomRole(array $data, int $organisationId, array $permissions): Role
    {
        // Create template data
        $templateData = [
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? $data['name'],
            'description' => $data['description'] ?? null,
            'level' => $data['level'] ?? 10, // Default level
            'organisation_id' => $organisationId,
            'is_system' => false,
            'can_be_deleted' => true,
            'scope' => 'organization'
        ];

        // Get permission IDs
        $permissionIds = Permission::whereIn('name', $permissions)
            ->pluck('id')
            ->toArray();

        // Create empty array if no permissions found
        if (empty($permissionIds)) {
            $permissionIds = [];
        }

        // Create the template
        $template = $this->templateService->createTemplate($templateData, $permissionIds);

        // Create the role
        return $this->templateService->createRoleFromTemplate($template, $organisationId);
    }

    /**
     * Create an override role for a system template
     *
     * @param int $systemTemplateId System template ID
     * @param int $organisationId Organization ID
     * @param array $data Override data (display_name, description)
     * @param array|null $permissions Array of permission names (null to keep system permissions)
     * @return array Contains 'role' and migration information
     * @throws \Throwable
     */
    public function createSystemRoleOverride(
        int $systemTemplateId,
        int $organisationId,
        array $data = [],
        ?array $permissions = null
    ): array
    {
        // Find the system template
        $systemTemplate = RoleTemplate::where('id', $systemTemplateId)
            ->where('is_system', true)
            ->first();

        if (!$systemTemplate) {
            throw new \Exception('Invalid system template');
        }

        // Check if an override template already exists
        $existingOverride = RoleTemplate::where('name', $systemTemplate->name)
            ->where('organisation_id', $organisationId)
            ->first();

        if ($existingOverride) {
            throw new \Exception('An override for this template already exists');
        }

        // Find the system role
        $systemRole = Role::whereNull('organisation_id')
            ->where('template_id', $systemTemplate->id)
            ->first();

        // If not found with NULL org_id, use the organization's existing role
        if (!$systemRole) {
            $systemRole = Role::where('organisation_id', $organisationId)
                ->where('template_id', $systemTemplate->id)
                ->where('overrides_system', false) // Must not already be an override
                ->first();
        }

        if (!$systemRole) {
            throw new \Exception('System role not found');
        }

        // Check if role override already exists
        $existingRoleOverride = Role::where('organisation_id', $organisationId)
            ->where('system_role_id', $systemRole->id)
            ->where('overrides_system', true)
            ->first();

        if ($existingRoleOverride) {
            throw new \Exception('A role override already exists');
        }

        // Create override data
        $overrideData = [
            'display_name' => $data['display_name'] ?? $systemTemplate->display_name,
            'description' => $data['description'] ?? $systemTemplate->description,
        ];

        // Convert permissions to IDs if provided
        $permissionIds = null;
        if ($permissions !== null) {
            $permissionIds = Permission::whereIn('name', $permissions)
                ->pluck('id')
                ->toArray();
        }

        // Create the template override
        $template = $this->templateService->createSystemTemplateOverride(
            $systemTemplate,
            $organisationId,
            $overrideData,
            $permissionIds
        );

        // Create the role
        $role = $this->templateService->createRoleFromTemplate(
            $template,
            $organisationId,
            true, // overrides system
            $systemRole->id // system role ID
        );

        // Migrate users from system role to the new role
        $migrationResult = $this->templateService->migrateUsersToTemplateOverride(
            $systemTemplate,
            $template,
            $organisationId
        );

        return [
            'role' => $role,
            'migrated_users' => $migrationResult['migrated'] ?? 0
        ];
    }

    /**
     * Revert an organization role to its system counterpart
     *
     * @param int $roleId Role ID
     * @param int $organisationId Organization ID
     * @return array Migration information
     * @throws \Throwable
     */
    public function revertRoleToSystem(int $roleId, int $organisationId): array
    {
        // Find the role
        $role = Role::where('id', $roleId)
            ->where('organisation_id', $organisationId)
            ->with('template')
            ->first();

        if (!$role) {
            throw new \Exception('Role not found');
        }

        // Check if this role overrides a system role
        if (!$role->overrides_system || !$role->system_role_id) {
            throw new \Exception('This role does not override a system role');
        }

        // Get the template
        $template = $role->template;

        if (!$template || $template->is_system || $template->organisation_id !== $organisationId) {
            throw new \Exception('Invalid template for this role');
        }

        // Revert to system template
        return $this->templateService->revertToSystemTemplate($template, $organisationId);
    }

    /**
     * Add permissions to a role
     *
     * @param int $roleId Role ID
     * @param int $organisationId Organization ID
     * @param array $permissions Permission names to add
     * @return Role Updated role
     * @throws \Throwable
     */
    public function addPermissionsToRole(int $roleId, int $organisationId, array $permissions): Role
    {
        // Find the role
        $role = Role::where('id', $roleId)
            ->where('organisation_id', $organisationId)
            ->with('template')
            ->first();

        if (!$role) {
            throw new \Exception('Role not found');
        }

        $template = $role->template;

        // If system template, create override first
        if ($template->is_system) {
            // Get current permissions plus new ones
            $currentPermissionNames = $template->permissions->pluck('name')->toArray();
            $allPermissions = array_unique(array_merge($currentPermissionNames, $permissions));

            // Create override
            $result = $this->createSystemRoleOverride(
                $template->id,
                $organisationId,
                [], // No need to change display name or description
                $allPermissions
            );

            return $result['role'];
        }

        // For existing organization template, just add permissions
        if (!$template->is_system && $template->organisation_id === $organisationId) {
            $permissionIds = Permission::whereIn('name', $permissions)
                ->pluck('id')
                ->toArray();

            $this->templateService->addPermissionsToTemplate($template, $permissionIds);
            $role->refresh();
            return $role;
        }

        throw new \Exception('Cannot modify template that does not belong to this organization');
    }

    /**
     * Remove permissions from a role
     *
     * @param int $roleId Role ID
     * @param int $organisationId Organization ID
     * @param array $permissions Permission names to remove
     * @return Role Updated role
     * @throws \Throwable
     */
    public function removePermissionsFromRole(int $roleId, int $organisationId, array $permissions): Role
    {
        // Find the role
        $role = Role::where('id', $roleId)
            ->where('organisation_id', $organisationId)
            ->with('template')
            ->first();

        if (!$role) {
            throw new \Exception('Role not found');
        }

        $template = $role->template;

        // If system template, create override first
        if ($template->is_system) {
            // Get current permissions minus removed ones
            $currentPermissionNames = $template->permissions->pluck('name')->toArray();
            $remainingPermissions = array_diff($currentPermissionNames, $permissions);

            // Ensure we're not removing all permissions
            if (empty($remainingPermissions)) {
                throw new \Exception('Cannot remove all permissions from a role');
            }

            // Create override
            $result = $this->createSystemRoleOverride(
                $template->id,
                $organisationId,
                [], // No need to change display name or description
                $remainingPermissions
            );

            return $result['role'];
        }

        // For existing organization template, just remove permissions
        if (!$template->is_system && $template->organisation_id === $organisationId) {
            $permissionIds = Permission::whereIn('name', $permissions)
                ->pluck('id')
                ->toArray();

            // Check if we're not removing all permissions
            $remainingCount = $template->permissions()->count() - count($permissionIds);
            if ($remainingCount < 1) {
                throw new \Exception('Cannot remove all permissions from a role');
            }

            $this->templateService->removePermissionsFromTemplate($template, $permissionIds);
            $role->refresh();
            return $role;
        }

        throw new \Exception('Cannot modify template that does not belong to this organization');
    }

    /**
     * Get all available roles for an organization,
     * combining organization-specific roles with non-overridden system roles
     *
     * @param int $organisationId Organization ID
     * @return Collection
     */
    public function getAvailableRoles(int $organisationId): Collection
    {
        // First, get all organization-specific roles
        $orgRoles = Role::where('organisation_id', $organisationId)
            ->with('template.permissions')
            ->get();

        // Get IDs of system roles that have been overridden
        $overriddenSystemRoleIds = $orgRoles
            ->where('overrides_system', true)
            ->pluck('system_role_id')
            ->filter() // Remove nulls
            ->toArray();

        // Get system roles that haven't been overridden
        $systemRoles = Role::whereNull('organisation_id')
            ->whereNotIn('id', $overriddenSystemRoleIds)
            ->with('template.permissions')
            ->get();

        // Combine org-specific and remaining system roles
        return $orgRoles->concat($systemRoles);
    }

    /**
     * Update a role's metadata
     *
     * @param int $roleId Role ID
     * @param int $organisationId Organization ID
     * @param array $data Update data
     * @param array|null $permissions Permission names (null to keep existing)
     * @return Role Updated role
     * @throws \Throwable
     */
    public function updateRole(int $roleId, int $organisationId, array $data, ?array $permissions = null): Role
    {
        // Find the role
        $role = Role::where('id', $roleId)
            ->where('organisation_id', $organisationId)
            ->with('template')
            ->first();

        if (!$role) {
            throw new \Exception('Role not found');
        }

        $template = $role->template;
        $updateData = array_intersect_key($data, [
            'display_name' => true,
            'description' => true
        ]);

        // For system template, create an override
        if ($template->is_system) {
            // Only proceed if we have changes to make
            if (!empty($updateData) || $permissions !== null) {
                $permissionNames = $permissions;

                // Create override
                $result = $this->createSystemRoleOverride(
                    $template->id,
                    $organisationId,
                    $updateData,
                    $permissionNames
                );

                return $result['role']->fresh(['template.permissions']);
            }

            return $role;
        }

        // For organization template, update directly
        if (!$template->is_system && $template->organisation_id === $organisationId) {
            // Update template data
            if (!empty($updateData)) {
                $this->templateService->updateTemplate($template, $updateData);
            }

            // Update permissions if provided
            if ($permissions !== null) {
                $permissionIds = Permission::whereIn('name', $permissions)
                    ->pluck('id')
                    ->toArray();

                $this->templateService->updateTemplate($template, [], $permissionIds);
            }

            return $role->fresh(['template.permissions']);
        }

        throw new \Exception('Cannot modify template that does not belong to this organization');
    }
}
