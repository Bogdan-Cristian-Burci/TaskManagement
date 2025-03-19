<?php

namespace App\Traits;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;

trait OrganisationHelpers
{
    /**
     * Helper method to get organisation ID.
     *
     * @param int|Organisation|null $organisation
     * @return int|null
     */
    protected function getOrganisationId(int|Organisation $organisation = null): ?int
    {
        if ($organisation === null) {
            return $this->organisation_id;
        }

        if ($organisation instanceof Organisation) {
            return $organisation->id;
        }

        return (int) $organisation;
    }

    /**
     * Helper method to get role ID.
     *
     * @param mixed $role
     * @param int|null $organisationId
     * @return int|null
     */
    protected function getRoleId(mixed $role, ?int $organisationId = null): ?int
    {
        if (is_int($role)) {
            return $role;
        }

        if ($role instanceof Role) {
            return $role->id;
        }

        if (is_string($role) && $organisationId) {
            $roleModel = Role::where('name', $role)
                ->where('organisation_id', $organisationId)
                ->first();

            return $roleModel ? $roleModel->id : null;
        }

        return null;
    }

    /**
     * Helper method to get permission ID.
     *
     * @param mixed $permission
     * @return int|null
     */
    protected function getPermissionId(mixed $permission): ?int
    {
        if (is_int($permission)) {
            return $permission;
        }

        if ($permission instanceof Permission) {
            return $permission->id;
        }

        if (is_string($permission)) {
            $permissionModel = Permission::where('name', $permission)->first();
            return $permissionModel ? $permissionModel->id : null;
        }

        return null;
    }

    /**
     * Add permission check constraints to a query.
     *
     * @param Builder $query
     * @param mixed $permission
     * @return void
     */
    protected function addPermissionConstraint(Builder $query, mixed $permission): void
    {
        if (is_string($permission)) {
            $query->where('permissions.name', $permission);
        } elseif (is_int($permission)) {
            $query->where('permissions.id', $permission);
        } elseif ($permission instanceof Permission) {
            $query->where('permissions.id', $permission->id);
        }
    }
}
