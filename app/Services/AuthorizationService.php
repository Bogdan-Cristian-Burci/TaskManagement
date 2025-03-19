<?php

namespace App\Services;

use App\Models\Organisation;
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
}
