<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasPermissions
{
    use OrganisationHelpers;

    /**
     * Check if user has a specific permission in the specified organization.
     *
     * @param mixed $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasPermission(mixed $permission, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);

        // First check for explicit denials
        $denied = $this->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', false)
            ->where(function($q) use ($permission) {
                $this->addPermissionConstraint($q, $permission);
            })
            ->exists();

        if ($denied) {
            return false;
        }

        // Then check for explicit grants
        $directGrant = $this->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', true)
            ->where(function($q) use ($permission) {
                $this->addPermissionConstraint($q, $permission);
            })
            ->exists();

        if ($directGrant) {
            return true;
        }

        // Finally check role-based permissions
        $hasPermissionViaRole = $this->roles()
            ->whereHas('organisation', function ($q) use ($organisationId) {
                $q->where('organisations.id', $organisationId);
            })
            ->whereHas('permissions', function ($q) use ($permission) {
                $this->addPermissionConstraint($q, $permission);
            })
            ->exists();

        return $hasPermissionViaRole;
    }

    /**
     * Check if user has any of the specified permissions.
     *
     * @param array $permissions Array of permission names, IDs, or objects
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasAnyPermission(array $permissions, int|Organisation $organisation = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $organisation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the specified permissions.
     *
     * @param array $permissions Array of permission names, IDs, or objects
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasAllPermissions(array $permissions, int|Organisation $organisation = null): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission, $organisation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Grant a direct permission to user in specified organization.
     *
     * @param mixed $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function grantPermission(mixed $permission, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        $permissionId = $this->getPermissionId($permission);

        if (!$permissionId) {
            return false;
        }

        // Remove any existing denial first
        $this->permissions()
            ->wherePivot('permission_id', $permissionId)
            ->wherePivot('organisation_id', $organisationId)
            ->detach();

        // Then add the grant
        $this->permissions()->attach($permissionId, [
            'organisation_id' => $organisationId,
            'grant' => true
        ]);

        return true;
    }

    /**
     * Deny a permission to user in specified organization.
     *
     * @param mixed $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function denyPermission($permission, $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        $permissionId = $this->getPermissionId($permission);

        if (!$permissionId) {
            return false;
        }

        // Remove any existing grant first
        $this->permissions()
            ->wherePivot('permission_id', $permissionId)
            ->wherePivot('organisation_id', $organisationId)
            ->detach();

        // Then add the denial
        $this->permissions()->attach($permissionId, [
            'organisation_id' => $organisationId,
            'grant' => false
        ]);

        return true;
    }

    /**
     * Remove a direct permission from user.
     *
     * @param mixed $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function removePermission($permission, $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        $permissionId = $this->getPermissionId($permission);

        if (!$permissionId) {
            return false;
        }

        $this->permissions()->wherePivot('permission_id', $permissionId)
                          ->wherePivot('organisation_id', $organisationId)
                          ->detach();

        return true;
    }

    /**
     * Get all effective permissions for this user in an organization.
     *
     * @param int|Organisation|null $organisation Organisation context
     * @return Collection
     */
    public function getAllPermissions(int|Organisation $organisation = null): Collection
    {
        $organisationId = $this->getOrganisationId($organisation);

        // Get denied permissions
        $deniedIds = $this->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', false)
            ->pluck('permissions.id')
            ->toArray();

        // Get direct granted permissions
        $directPermissions = $this->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', true)
            ->get();

        // Get role permissions
        $rolePermissionsQuery = Permission::whereHas('roles', function($query) use ($organisationId) {
            $query->whereHas('users', function($q) {
                $q->where('users.id', $this->id);
            })
            ->where('roles.organisation_id', $organisationId);
        });

        // Exclude denied permissions
        if (!empty($deniedIds)) {
            $rolePermissionsQuery->whereNotIn('permissions.id', $deniedIds);
        }

        $rolePermissions = $rolePermissionsQuery->get();

        // Merge both sets
        return $directPermissions->merge($rolePermissions)->unique('id');
    }

    /**
     * Backwards compatibility - alias for hasPermission
     */
    public function hasOrganisationPermission($permission, $organisation = null): bool
    {
        return $this->hasPermission($permission, $organisation);
    }

    /**
     * Backwards compatibility - alias for grantPermission
     */
    public function addPermissionOverride(string $permission, $organisation = null, string $type = 'grant'): self
    {
        if ($type == 'grant') {
            $this->grantPermission($permission, $organisation);
        } else {
            $this->denyPermission($permission, $organisation);
        }

        return $this;
    }

    /**
     * Backwards compatibility - alias for removePermission
     */
    public function removePermissionOverride(string $permission, $organisation = null): self
    {
        $this->removePermission($permission, $organisation);
        return $this;
    }

    /**
     * Get all permissions for this user in the current organization.
     */
    public function getOrganisationPermissionsAttribute(): array
    {
        if (!$this->organisation_id) {
            return [];
        }

        return $this->getAllPermissions($this->organisation_id)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get permission overrides for this user in the current organization.
     */
    public function getPermissionOverridesAttribute(): array
    {
        if (!$this->organisation_id) {
            return ['grant' => [], 'deny' => []];
        }

        $grant = $this->permissions()
            ->wherePivot('organisation_id', $this->organisation_id)
            ->wherePivot('grant', true)
            ->pluck('name')
            ->toArray();

        $deny = $this->permissions()
            ->wherePivot('organisation_id', $this->organisation_id)
            ->wherePivot('grant', false)
            ->pluck('name')
            ->toArray();

        return [
            'grant' => $grant,
            'deny' => $deny
        ];
    }
}
