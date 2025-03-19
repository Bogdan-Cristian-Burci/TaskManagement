<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{

    use OrganisationHelpers;

    /**
     * Check if user has a specific role in the specified organization.
     *
     * @param mixed $role Role name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasRole(mixed $role, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);

        $query = $this->roles()
            ->whereHas('organisation', function ($q) use ($organisationId) {
                $q->where('organisations.id', $organisationId);
            });

        if (is_string($role)) {
            return $query->where('roles.name', $role)->exists();
        }

        if (is_int($role)) {
            return $query->where('roles.id', $role)->exists();
        }

        if ($role instanceof Role) {
            return $query->where('roles.id', $role->id)->exists();
        }

        return false;
    }

    /**
     * Check if user has any of the specified roles.
     *
     * @param array $roles Array of role names, IDs, or objects
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasAnyRole(array $roles, int|Organisation $organisation = null): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $organisation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the specified roles.
     *
     * @param array $roles Array of role names, IDs, or objects
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasAllRoles(array $roles, int|Organisation $organisation = null): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role, $organisation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attach a role to user in specified organization.
     *
     * @param mixed $role Role name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function attachRole(mixed $role, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);

        $roleId = $this->getRoleId($role, $organisationId);
        if (!$roleId) {
            return false;
        }

        if (!$this->roles()->where('roles.id', $roleId)->exists()) {
            $this->roles()->attach($roleId);
            return true;
        }

        return false;
    }

    /**
     * Detach a role from user.
     *
     * @param mixed $role Role name, ID, or object
     * @return bool
     */
    public function detachRole(mixed $role): bool
    {
        $roleId = $this->getRoleId($role);
        if (!$roleId) {
            return false;
        }

        $this->roles()->detach($roleId);
        return true;
    }

    /**
     * Get all roles for the user in a specific organization.
     *
     * @param int|Organisation|null $organisation Organisation context
     * @return Collection
     */
    public function getRolesInOrganisation(int|Organisation $organisation = null): Collection
    {
        $organisationId = $this->getOrganisationId($organisation);

        return $this->roles()
            ->where('organisation_id', $organisationId)
            ->get();
    }

    /**
     * Check if the user has a role in a specific organization.
     *
     * @param mixed $role Role name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasRoleInOrganisation(mixed $role, int|Organisation $organisation = null): bool
    {
        // Just an alias for hasRole that maintains backwards compatibility
        return $this->hasRole($role, $organisation);
    }

    /**
     * Sync roles for the user in an organization.
     *
     * @param array $roles Array of role IDs or names
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function syncRoles(array $roles, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);

        $roleIds = [];
        foreach ($roles as $role) {
            $roleId = $this->getRoleId($role, $organisationId);
            if ($roleId) {
                $roleIds[] = $roleId;
            }
        }

        // Detach all current roles in this org
        $this->roles()
            ->whereHas('organisation', function ($q) use ($organisationId) {
                $q->where('organisations.id', $organisationId);
            })
            ->detach();

        // Attach the new roles
        if (!empty($roleIds)) {
            $this->roles()->attach($roleIds);
        }

        return true;
    }

    /**
     * Get the highest level role for this user in an organization.
     *
     * @param int|Organisation|null $organisation Organisation context
     * @return Role|null
     */
    public function getHighestRole(int|Organisation $organisation = null): ?Role
    {
        $organisationId = $this->getOrganisationId($organisation);

        return $this->roles()
            ->where('organisation_id', $organisationId)
            ->orderByDesc('level')
            ->first();
    }

}
