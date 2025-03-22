<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTemplate;
use Illuminate\Support\Facades\DB;

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
            $existingPermissionIds = $template->permissions()->select('permissions.id')->pluck('id')->toArray();

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

    /**
     * Migrate users from a system template to an organization override
     *
     * @param RoleTemplate $systemTemplate
     * @param RoleTemplate $overrideTemplate
     * @param int $organisationId
     * @return array Information about the migration
     * @throws \Throwable
     */
    public function migrateUsersToTemplateOverride(
        RoleTemplate $systemTemplate,
        RoleTemplate $overrideTemplate,
        int $organisationId
    ): array {
        // Find or create the roles
        $systemRole = Role::whereNull('organisation_id')
            ->where('template_id', $systemTemplate->id)
            ->first();

        if (!$systemRole) {
            return ['migrated' => 0, 'error' => 'System role not found'];
        }

        $orgRole = Role::firstOrCreate(
            [
                'template_id' => $overrideTemplate->id,
                'organisation_id' => $organisationId,
            ],
            [
                'overrides_system' => true,
                'system_role_id' => $systemRole->id
            ]
        );

        // Find users with the system role in this organization
        $usersWithSystemRole = DB::table('model_has_roles')
            ->where('role_id', $systemRole->id)
            ->where('organisation_id', $organisationId)
            ->get();

        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($usersWithSystemRole as $assignment) {
                // Remove system role assignment
                DB::table('model_has_roles')
                    ->where('role_id', $systemRole->id)
                    ->where('model_id', $assignment->model_id)
                    ->where('model_type', $assignment->model_type)
                    ->where('organisation_id', $organisationId)
                    ->delete();

                // Add organization role assignment
                DB::table('model_has_roles')->insert([
                    'role_id' => $orgRole->id,
                    'model_id' => $assignment->model_id,
                    'model_type' => $assignment->model_type,
                    'organisation_id' => $organisationId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $count++;
            }

            DB::commit();
            return ['migrated' => $count];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['migrated' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revert from override template to system template and migrate users
     *
     * @param RoleTemplate $overrideTemplate The organization-specific template to revert
     * @param int $organisationId The organization ID
     * @return array Information about the migration
     * @throws \Throwable
     */
    public function revertToSystemTemplate(RoleTemplate $overrideTemplate, int $organisationId): array
    {
        // Validate that this is actually an override template
        if ($overrideTemplate->is_system || $overrideTemplate->organisation_id !== $organisationId) {
            return ['migrated' => 0, 'error' => 'Template is not an organization override'];
        }

        // Find the corresponding system template
        $systemTemplate = RoleTemplate::where('name', $overrideTemplate->name)
            ->where('is_system', true)
            ->whereNull('organisation_id')
            ->first();

        if (!$systemTemplate) {
            return ['migrated' => 0, 'error' => 'Corresponding system template not found'];
        }

        // Find the roles based on these templates
        $orgRole = Role::where('template_id', $overrideTemplate->id)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$orgRole) {
            // Nothing to migrate, just delete the template
            $this->deleteTemplate($overrideTemplate);
            return ['migrated' => 0, 'template_deleted' => true];
        }

        // Find or create the system role
        $systemRole = Role::whereNull('organisation_id')
            ->where('template_id', $systemTemplate->id)
            ->first();

        if (!$systemRole) {
            // System role doesn't exist, create it (this is unlikely but handled for safety)
            $systemRole = Role::create([
                'template_id' => $systemTemplate->id,
                'organisation_id' => null,
                'overrides_system' => false,
                'system_role_id' => null,
                'guard_name' => 'api'
            ]);
        }

        // Find users with the organization-specific role
        $usersWithOrgRole = DB::table('model_has_roles')
            ->where('role_id', $orgRole->id)
            ->where('organisation_id', $organisationId)
            ->get();

        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($usersWithOrgRole as $assignment) {
                // Remove organization role assignment
                DB::table('model_has_roles')
                    ->where('role_id', $orgRole->id)
                    ->where('model_id', $assignment->model_id)
                    ->where('model_type', $assignment->model_type)
                    ->where('organisation_id', $organisationId)
                    ->delete();

                // Add system role assignment
                DB::table('model_has_roles')->insert([
                    'role_id' => $systemRole->id,
                    'model_id' => $assignment->model_id,
                    'model_type' => $assignment->model_type,
                    'organisation_id' => $organisationId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $count++;
            }

            // Delete the organization-specific role
            $orgRole->delete();

            // Delete the override template
            $this->deleteTemplate($overrideTemplate);

            DB::commit();
            return [
                'migrated' => $count,
                'template_deleted' => true,
                'system_template_id' => $systemTemplate->id
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
