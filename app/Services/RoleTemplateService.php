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
     * Delete a role template.
     */
    public function deleteTemplate(RoleTemplate $template): bool
    {
        // Cannot delete system templates
        if ($template->is_system) {
            return false;
        }

        $template->delete();
        return true;
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
