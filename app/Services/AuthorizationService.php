<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\User;

class AuthorizationService
{
    /**
     * Check if a user has permission in an organization
     */
    public function hasOrganisationPermission($user, $permission, $organisation)
    {
        // Set context for standard permission checks
        \App\Services\OrganizationContext::setCurrentOrganization(
            $organisation instanceof \App\Models\Organisation ? $organisation->id : $organisation
        );

        $result = $user->hasOrganisationPermission($permission, $organisation);

        // Clear context after check
        \App\Services\OrganizationContext::clear();

        return $result;
    }

    /**
     * Check if user has sufficient role level in organization
     */
    public function hasOrganisationRoleLevel(User $user, int $requiredLevel, Organisation $organisation): bool
    {
        // Get the user's role in the organization
        $role = $user->roles()
            ->where('organisation_id', $organisation->id)
            ->orderByDesc('level')
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
        return $user->hasRole('Owner', $organisation->id);
    }

    /**
     * Check if user can manage another user in an organization
     * Higher-level roles can manage lower-level roles
     */
    public function canManageUserInOrganisation(User $manager, User $user, Organisation $organisation): bool
    {
        // Super admins can manage anyone
        if ($manager->hasRole('super-admin')) {
            return true;
        }

        // Get the manager's highest role level in the organization
        $managerRole = $manager->roles()
            ->where('organisation_id', $organisation->id)
            ->orderByDesc('level')
            ->first();

        // Get the user's highest role level in the organization
        $userRole = $user->roles()
            ->where('organisation_id', $organisation->id)
            ->orderByDesc('level')
            ->first();

        if (!$managerRole || !$userRole) {
            return false;
        }

        // Manager can only manage users with lower role levels
        return $managerRole->level > $userRole->level;
    }
}
