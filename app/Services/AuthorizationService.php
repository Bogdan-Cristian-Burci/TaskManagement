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

        // First check if there's a 'deny' override for this permission
        if ($this->hasPermissionOverride($user, $permission, $organisation->id, 'deny')) {
            return false;
        }

        // Then check if there's a 'grant' override
        if ($this->hasPermissionOverride($user, $permission, $organisation->id, 'grant')) {
            return true;
        }

        // Check if permission is in user's role template
        if ($this->checkTemplatePermission($user, $permission, $organisation->id)) {
            return true;
        }

        return $this->checkDirectPermissionInOrg($user, $permission, $organisation->id);
    }

    /**
     * Check if user has a permission override of specified type
     */
    protected function hasPermissionOverride(User $user, string $permission, int $orgId, string $type): bool
    {
        return DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $user->id)
            ->where('model_has_permissions.model_type', get_class($user))
            ->where('model_has_permissions.organisation_id', $orgId)
            ->where('permissions.name', $permission)
            ->where('model_has_permissions.type', $type)
            ->exists();
    }

    /**
     * Check if permission exists in user's role template
     */
    protected function checkTemplatePermission(User $user, string $permission, int $orgId): bool
    {
        // Get user's role in this organization
        $role = $user->roles()
            ->where('organisation_id', $orgId)
            ->first();

        if (!$role || !$role->template_id) {
            return false;
        }

        // Get role template
        $template = DB::table('role_templates')
            ->where('id', $role->template_id)
            ->where('organisation_id', $orgId)
            ->first();

        if (!$template) {
            return false;
        }

        // Check if permission is in template
        $permissions = json_decode($template->permissions, true);
        return in_array($permission, $permissions);
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
        return $organisation->owner_id === $user->id;
    }

    /**
     * Check if user can manage another user in an organization
     * Higher-level roles can manage lower-level roles
     */
    public function canManageUserInOrganisation(User $manager, User $user, Organisation $organisation): bool
    {
        // If they're the same user, can't manage themselves
        if ($manager->id === $user->id) {
            return false;
        }

        // Organization owner can manage everyone
        if ($this->isOrganisationOwner($manager, $organisation)) {
            return true;
        }

        // Super admins can manage anyone except the owner
        if ($manager->hasRoleInOrganisation('super-admin', $organisation->id)) {
            return !$this->isOrganisationOwner($user, $organisation);
        }

        // Admins can manage regular users but not other admins or owners
        if ($manager->hasRoleInOrganisation('admin', $organisation->id)) {
            return !$this->isOrganisationOwner($user, $organisation) &&
                !$user->hasRoleInOrganisation(['admin', 'super-admin'], $organisation->id);
        }

        // Non-admins can't manage others
        return false;
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
        $allPermissions = [];
        $templatePermissions = [];
        $directPermissions = [];
        $overridePermissions = ['grant' => [], 'deny' => []];

        // Get role and its template
        $role = $user->roles()
            ->where('organisation_id', $organisationId)
            ->first();

        if ($role && $role->template_id) {
            $template = DB::table('role_templates')
                ->where('id', $role->template_id)
                ->first();

            if ($template) {
                $templatePermissions = json_decode($template->permissions, true);
            }
        }

        // Get direct permissions from role_has_permissions
        $directPermissions = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('model_has_roles', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', get_class($user))
            ->where('model_has_roles.organisation_id', $organisationId)
            ->pluck('permissions.name')
            ->toArray();

        // Get overrides
        $overrides = DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $user->id)
            ->where('model_has_permissions.model_type', get_class($user))
            ->where('model_has_permissions.organisation_id', $organisationId)
            ->select('permissions.name', 'model_has_permissions.type')
            ->get();

        foreach ($overrides as $override) {
            $overridePermissions[$override->type][] = $override->name;
        }

        // Calculate effective permissions
        $basePermissions = array_merge($templatePermissions, $directPermissions);

        // Add grants
        $allPermissions = array_merge($basePermissions, $overridePermissions['grant']);

        // Remove denies
        $allPermissions = array_diff($allPermissions, $overridePermissions['deny']);

        // Remove duplicates
        $allPermissions = array_unique($allPermissions);

        return $allPermissions;
    }
}
