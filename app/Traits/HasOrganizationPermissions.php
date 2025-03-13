<?php

namespace App\Traits;

use App\Models\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasOrganizationPermissions
{
    /**
     * Assign role to user in a specific organization
     *
     * @param mixed $role
     * @param int|Organisation $organisation
     * @return $this
     */
    public function assignOrganisationRole(mixed $role, int|Organisation $organisation) : self
    {
        $orgId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        // If the role is a Role object, extract its name and explicitly specify the guard_name
        if ($role instanceof \Spatie\Permission\Models\Role) {
            return $this->assignRole([$role->name, $role->guard_name, $orgId]);
        }

        return $this->assignRole([$role, $orgId]);
    }

    /**
     * Remove role from user in a specific organization
     *
     * @param mixed $role
     * @param int|Organisation $organisation
     * @return $this
     */
    public function removeOrganisationRole(mixed $role, int|Organisation $organisation) : self
    {
        $orgId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        return $this->removeRole([$role, $orgId]);
    }

    /**
     * Check if user has role in specific organization
     *
     * @param mixed $role
     * @param int|Organisation $organisation
     * @return bool
     */
    public function hasOrganisationRole(mixed $role, int|Organisation $organisation) : bool
    {
        $orgId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        return $this->hasRole([$role, $orgId]);
    }

    /**
     * Get all roles in a specific organization
     *
     * @param int|Organisation $organisation
     * @return Collection
     */
    public function getOrganisationRoles(int|Organisation $organisation) : Collection
    {
        $orgId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        // Get roles with the specified organization_id
        return $this->roles()->where('organisation_id', $orgId)->get();
    }

    /**
     * Check for permission in a specific organization
     *
     * @param array|string $permission
     * @param int|Organisation $organisation
     * @return bool
     */
    public function hasOrganisationPermission($permission, $organisation): bool
    {
        // Convert organization ID to Organization object if needed
        if (is_numeric($organisation)) {
            $organisationObj = Organisation::find($organisation);
            if (!$organisationObj) {
                return false; // Organization doesn't exist
            }
            $organisation = $organisationObj;
        }

        $orgId = $organisation->id;

        // If user is admin/super-admin in this organization, return true
        if ($this->hasRole(['admin', $orgId]) || $this->hasRole(['super-admin', $orgId])) {
            return true;
        }

        // If user is organization owner, return true
        if ($organisation->isOwner($this)) {
            return true;
        }

        // Check if user has permission in this organization
        if (is_array($permission)) {
            foreach ($permission as $perm) {
                if ($this->checkDirectPermissionInOrg($perm, $orgId)) {
                    return true;
                }
            }
            return false;
        }

        return $this->checkDirectPermissionInOrg($permission, $orgId);
    }

    /**
     * Check direct permission in organization
     *
     * @param string $permission
     * @param int $orgId
     * @return bool
     */
    protected function checkDirectPermissionInOrg(string $permission, int $orgId): bool
    {
        // First check direct permissions
        $directPermission = DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $this->id)
            ->where('model_has_permissions.model_type', get_class($this))
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
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.organisation_id', $orgId)
            ->where('permissions.name', $permission)
            ->exists();
    }
}
