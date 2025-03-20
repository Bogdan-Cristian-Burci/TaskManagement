<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\RoleTemplate;
use App\Models\User;
use App\Models\Role;

class AuthorizationService
{
    /**
     * Check if user has sufficient role level in organization
     */
    public function hasOrganisationRoleLevel(User $user, int $requiredLevel, int|Organisation $organisation): bool
    {
        $organisationId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        // Get the user's highest level role in the organization
        $role = $user->getRolesInOrganisation($organisationId)
            ->sortByDesc('level')
            ->first();

        if (!$role) {
            return false;
        }

        return $role->level >= $requiredLevel;
    }

    /**
     * Check if user is owner of an organization
     */
    public function isOrganisationOwner(User $user, Organisation $organisation): bool
    {
        return $organisation->owner_id === $user->id;
    }

    /**
     * Check if user can manage another user in an organization
     * Higher-level roles can manage lower-level roles
     */
    public function canManageUserInOrganisation(User $manager, User $user, int|Organisation $organisation): bool
    {
        $organisationId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        // If they're the same user, can't manage themselves
        if ($manager->id === $user->id) {
            return false;
        }

        // Organization owner can manage everyone
        if ($this->isOrganisationOwner($manager, Organisation::find($organisationId))) {
            return true;
        }

        // Get role levels
        $managerRole = $manager->getHighestRole($organisationId);
        $userRole = $user->getHighestRole($organisationId);

        if (!$managerRole) {
            return false;
        }

        // Can't manage if user has no role in this org
        if (!$userRole) {
            // But if manager is admin or higher, they can always add people
            return $managerRole->level >= 80; // Admin level
        }

        // Can only manage users with lower role levels
        return $managerRole->level > $userRole->level;
    }

    /**
     * Get effective permissions for a user in an organization context
     * This is useful for UI features showing what permissions a user has
     *
     * @param User $user
     * @param int $organisationId
     * @return array
     */
    public function getEffectivePermissions(User $user, int $organisationId): array
    {
        // Get denied permissions (user_permissions with grant=false)
        $deniedIds = $user->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', false)
            ->pluck('permissions.id')
            ->toArray();

        // Get direct granted permissions (user_permissions with grant=true)
        $grantedPermissions = $user->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', true)
            ->pluck('permissions.name')
            ->toArray();

        // Get roles for this user in this organization
        $roles = $user->roles()
            ->whereHas('organisation', function($q) use ($organisationId) {
                $q->where('organisations.id', $organisationId);
            })
            ->get();

        $rolePermissions = [];

        // Get permissions from each role
        foreach ($roles as $role) {
            // Get permissions directly attached to role
            $directRolePermissions = $role->permissions()
                ->whereNotIn('permissions.id', $deniedIds)
                ->pluck('permissions.name')
                ->toArray();

            $rolePermissions = array_merge($rolePermissions, $directRolePermissions);

            // If role uses a template, get template permissions too
            if ($role->template_id) {
                $template = RoleTemplate::find($role->template_id);
                if ($template) {
                    $templatePermissions = $template->permissions()
                        ->whereNotIn('permissions.id', $deniedIds)
                        ->pluck('permissions.name')
                        ->toArray();

                    $rolePermissions = array_merge($rolePermissions, $templatePermissions);
                }
            }
        }

        // Merge all permissions
        $allPermissions = array_merge($grantedPermissions, $rolePermissions);

        // Remove duplicates
        return array_values(array_unique($allPermissions));
    }

    /**
     * Assign role to a user from a template
     *
     * @param User $user The user to assign the role to
     * @param string $templateName The name of the template to use
     * @param int $organisationId The organization ID context
     * @return void
     * @throws \Exception If template not found
     */
    public function assignRoleFromTemplate(User $user, string $templateName, int $organisationId): void
    {
        // First try to find organization-specific template
        $template = RoleTemplate::where('name', $templateName)
            ->where('organisation_id', $organisationId)
            ->first();

        // If not found, fall back to system template
        if (!$template) {
            $template = RoleTemplate::where('name', $templateName)
                ->where('is_system', true)
                ->whereNull('organisation_id') // System templates have null organization
                ->first();
        }

        if (!$template) {
            throw new \Exception("Template '{$templateName}' not found");
        }

        // Get or create the role
        $role = Role::firstOrCreate(
            [
                'name' => $templateName,
                'organisation_id' => $organisationId
            ],
            [
                'name' => $templateName,
                'display_name' => $template->display_name,
                'description' => $template->description,
                'level' => $template->level,
                'organisation_id' => $organisationId,
                'template_id' => $template->id,
            ]
        );

        // Assign role to user
        $user->attachRole($role, $organisationId);
    }
}
