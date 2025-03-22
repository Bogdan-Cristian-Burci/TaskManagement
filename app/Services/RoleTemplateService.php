<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTemplate;

/**
 * Service class for role templates.
 */
class RoleTemplateService
{
    /**
     * Create a new role template.
     * @throws \Exception
     */
    public function createTemplate(array $data, array $permissionIds = [])
    {
        // Use RoleManager to validate that permissions exist in the system
        $roleManager = app(RoleManager::class);

        // Check for name conflicts with system templates
        if ($roleManager->roleExists($data['name']) && ($data['is_system'] ?? false)) {
            throw new \Exception("Cannot create custom template with reserved system name: {$data['name']}");
        }

        $template = RoleTemplate::create($data);

        if (!empty($permissionIds)) {
            $template->permissions()->attach($permissionIds);
        }

        return $template;
    }

    /**
     * Update a role template.
     */
    public function updateTemplate(RoleTemplate $template, array $data, array $permissionIds = null): RoleTemplate
    {
        $template->update($data);

        if ($permissionIds !== null) {
            $template->permissions()->sync($permissionIds);
        }

        return $template;
    }

    /**
     * Add permissions to an existing role template without removing existing ones.
     *
     * @param RoleTemplate $template
     * @param array $permissionIds Array of permission IDs to add
     * @return RoleTemplate
     */
    public function addPermissionsToTemplate(RoleTemplate $template, array $permissionIds): RoleTemplate
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

        return $template->fresh(['permissions']);
    }

    /**
     * Remove specific permissions from a template.
     *
     * @param RoleTemplate $template
     * @param array $permissionIds Array of permission IDs to remove
     * @return RoleTemplate
     */
    public function removePermissionsFromTemplate(RoleTemplate $template, array $permissionIds): RoleTemplate
    {
        if (!empty($permissionIds)) {
            $template->permissions()->detach($permissionIds);
        }

        return $template->fresh(['permissions']);
    }

    /**
     * Delete a role template.
     */
    public function deleteTemplate(RoleTemplate $template): bool
    {
        // Cannot delete system templates
        if ($template->is_system) {
            return false;
        }

        // First detach all permissions
        $template->permissions()->detach();

        // Then delete the template
        $template->delete();
        return true;
    }

    /**
     * Create an organization-specific override of a system template
     *
     * @param RoleTemplate $systemTemplate The system template to override
     * @param int $organisationId The organization ID
     * @param array $data Overriding data (display_name, description, etc)
     * @param array|null $permissionIds Specific permissions to use (null to copy from system template)
     * @return RoleTemplate
     * @throws \Exception If the template is not a system template
     */
    public function createSystemTemplateOverride(
        RoleTemplate $systemTemplate,
        int $organisationId,
        array $data,
        ?array $permissionIds = null
    ): RoleTemplate
    {
        // Ensure this is a system template
        if (!$systemTemplate->is_system) {
            throw new \Exception("Cannot override a non-system template");
        }

        // Create template data
        $templateData = [
            'name' => $systemTemplate->name,
            'display_name' => $data['display_name'] ?? $systemTemplate->display_name,
            'description' => $data['description'] ?? $systemTemplate->description,
            'level' => $systemTemplate->level,
            'organisation_id' => $organisationId,
            'is_system' => false,
            'can_be_deleted' => true,
            'scope' => 'organization'
        ];

        // If no permission IDs provided, copy from system template
        if ($permissionIds === null) {
            $permissionIds = $systemTemplate->permissions()->pluck('id')->toArray();
        }

        // Create the override template
        return $this->createTemplate($templateData, $permissionIds);
    }

    /**
     * Apply a template to create roles in all organizations.
     */
    public function applyTemplateToAllOrganisations(RoleTemplate $template): array
    {
        return $template->createOrganisationRoles();
    }

    /**
     * Apply all system templates to a new organization.
     */
    public function applySystemTemplatesToOrganisation(Organisation $organisation): array
    {
        return $organisation->createStandardRoles();
    }

    /**
     * Sync existing roles with their templates.
     */
    public function syncRolesWithTemplates(): int
    {
        $updated = 0;
        $roles = Role::whereNotNull('template_id')->get();

        foreach ($roles as $role) {
            if ($role->syncWithTemplate()) {
                $updated++;
            }
        }

        return $updated;
    }
}
