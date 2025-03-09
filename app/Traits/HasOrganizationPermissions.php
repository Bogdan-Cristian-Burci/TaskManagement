<?php

namespace App\Traits;

use App\Models\Organisation;
use Illuminate\Support\Collection;

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
    public function hasOrganisationPermission(array|string $permission, int|Organisation $organisation) : bool
    {
        $orgId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        // If user is admin/super-admin, return true
        if ($this->hasRole(['admin', 'super-admin'])) {
            return true;
        }

        // If user is organization owner, return true
        $org = $organisation instanceof Organisation ? $organisation : Organisation::find($orgId);
        if ($org && $org->isOwner($this)) {
            return true;
        }

        // Check if user has permission in this organization
        if (is_array($permission)) {
            $permParam = [];
            foreach ($permission as $perm) {
                $permParam[] = [$perm, $orgId];
            }
            return $this->hasPermissionTo($permParam);
        }

        return $this->hasPermissionTo([$permission, $orgId]);
    }
}
