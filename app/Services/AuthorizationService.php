<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthorizationService
{
    /**
     * Check if user has permission in organization context
     *
     * @param User $user
     * @param string $permission
     * @param Organisation|int $organisation
     * @return bool
     */
    public function hasOrganisationPermission(User $user, string $permission, $organisation): bool
    {
        // Convert organization ID to Organization object if needed
        if (is_numeric($organisation)) {
            $organisationObj = Organisation::find($organisation);
            if (!$organisationObj) {
                return false;
            }
            $organisation = $organisationObj;
        }

        // If user is admin/super-admin in this organization, return true
        if ($user->hasRoleInOrganisation(['admin', 'super-admin'], $organisation->id)) {
            return true;
        }

        // If user is organization owner, return true
        if ($organisation->isOwner($user)) {
            return true;
        }

        return $this->checkDirectPermissionInOrg($user, $permission, $organisation->id);
    }

    /**
     * Check direct permission in organization (extracted for reuse)
     *
     * @param User $user
     * @param string $permission
     * @param int $orgId
     * @return bool
     */
    protected function checkDirectPermissionInOrg(User $user, string $permission, int $orgId): bool
    {
        // First check direct permissions
        $directPermission = DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $user->id)
            ->where('model_has_permissions.model_type', get_class($user))
            ->where('model_has_permissions.organisation_id', $orgId)
            ->where('permissions.name', $permission)
            ->exists();

        if ($directPermission) {
            return true;
        }

        // Then check role-based permissions
        return DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('model_has_roles', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', get_class($user))
            ->where('model_has_roles.organisation_id', $orgId)
            ->where('permissions.name', $permission)
            ->exists();
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
