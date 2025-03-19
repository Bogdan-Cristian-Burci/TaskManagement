<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Collection;

class PermissionService
{
    /**
     * Get all permissions.
     */
    public function getAllPermissions(): Collection|\LaravelIdea\Helper\App\Models\_IH_Permission_C|array
    {
        return Permission::all();
    }

    /**
     * Get a user's permissions in an organization.
     */
    public function getUserPermissions(User $user, $organisationId)
    {
        // Get direct permissions
        $directPermissions = $user->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', true)
            ->get();

        // Get denied permissions
        $deniedPermissions = $user->permissions()
            ->wherePivot('organisation_id', $organisationId)
            ->wherePivot('grant', false)
            ->pluck('id')
            ->toArray();

        // Get role-based permissions
        $rolePermissionsQuery = Permission::whereHas('roles', function($query) use ($user, $organisationId) {
            $query->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            })
                ->where('roles.organisation_id', $organisationId);
        });

        // Exclude denied permissions
        if (!empty($deniedPermissions)) {
            $rolePermissionsQuery->whereNotIn('permissions.id', $deniedPermissions);
        }

        $rolePermissions = $rolePermissionsQuery->get();

        // Merge and return unique permissions
        return $directPermissions->merge($rolePermissions)->unique('id');
    }

    /**
     * Get a role's permissions.
     */
    public function getRolePermissions(Role $role)
    {
        return $role->permissions;
    }

    /**
     * Grant permissions to a role.
     */
    public function grantPermissionsToRole(Role $role, array $permissionIds): ?Role
    {
        // Get current permissions
        $currentPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        // Add new permissions
        $newPermissionIds = array_diff($permissionIds, $currentPermissionIds);
        if (!empty($newPermissionIds)) {
            $role->permissions()->attach($newPermissionIds);
        }

        return $role->fresh();
    }

    /**
     * Revoke permissions from a role.
     */
    public function revokePermissionsFromRole(Role $role, array $permissionIds): ?Role
    {
        $role->permissions()->detach($permissionIds);
        return $role->fresh();
    }

    /**
     * Create date/time: 2025-03-19 11:08:30
     * Update a role's permissions.
     */
    public function syncRolePermissions(Role $role, array $permissionIds): ?Role
    {
        $role->permissions()->sync($permissionIds);
        return $role->fresh();
    }

    /**
     * Grant permissions to a user in an organization.
     */
    public function grantPermissionsToUser(User $user, array $permissionIds, $organisationId): ?User
    {
        foreach ($permissionIds as $permissionId) {
            $user->permissions()->detach([
                $permissionId => ['organisation_id' => $organisationId]
            ]);

            $user->permissions()->attach($permissionId, [
                'organisation_id' => $organisationId,
                'grant' => true
            ]);
        }

        return $user->fresh();
    }

    /**
     * Deny permissions to a user in an organization.
     */
    public function denyPermissionsToUser(User $user, array $permissionIds, $organisationId): ?User
    {
        foreach ($permissionIds as $permissionId) {
            $user->permissions()->detach([
                $permissionId => ['organisation_id' => $organisationId]
            ]);

            $user->permissions()->attach($permissionId, [
                'organisation_id' => $organisationId,
                'grant' => false
            ]);
        }

        return $user->fresh();
    }

    /**
     * Remove direct permissions from a user in an organization.
     */
    public function removePermissionsFromUser(User $user, array $permissionIds, $organisationId): ?User
    {
        $user->permissions()->detach($permissionIds, [
            'organisation_id' => $organisationId
        ]);

        return $user->fresh();
    }
}
