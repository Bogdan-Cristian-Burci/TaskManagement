<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;

trait HasOrganizationRoles
{
    use HasRoles {
        assignRole as spatieAssignRole;
        hasRole as spatieHasRole;
        roles as spatieRoles;
        getPermissionsViaRoles as spatieGetPermissionsViaRoles;
    }

    /**
     * Override the roles relationship to include organization context
     */
    public function roles(): MorphToMany
    {
        $relationship = $this->spatieRoles();

        if ($this->organisation_id && config('permission.teams', false)) {
            $relationship->where('model_has_roles.organisation_id', $this->organisation_id);
        }

        return $relationship;
    }

    /**
     * Override hasRole to handle organization context
     */
    public function hasRole($roles, $guard = null): bool
    {
        // If no organization context needed, use original method
        if (!$this->organisation_id || !config('permission.teams', false)) {
            return $this->spatieHasRole($roles, $guard);
        }

        // Use direct DB query with organization context
        return $this->hasRoleWithOrganization($roles, $this->organisation_id, $guard);
    }

    /**
     * Helper method to check roles in a specific organization
     */
    protected function hasRoleWithOrganization($roles, $organizationId, $guard = null)
    {
        // Normalize input
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        $roles = is_array($roles) ? $roles : [$roles];

        // Direct DB query that includes organization_id
        $count = DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.organisation_id', $organizationId)
            ->whereIn('roles.name', $roles)
            ->count();

        return $count > 0;
    }

    /**
     * Assign the given role to the model in the current organization
     */
    public function assignRole(...$roles)
    {
        if (!$this->organisation_id || !config('permission.teams', false)) {
            return $this->spatieAssignRole(...$roles);
        }

        $roleModels = [];

        // Flatten array
        $roles = collect($roles)->flatten()->toArray();

        // Convert all role inputs to role models
        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleModel = $this->getRoleByName($role);
                if ($roleModel) {
                    $roleModels[] = $roleModel;
                }
            } else {
                $roleModels[] = $role;
            }
        }

        // Assign each role with organization context
        foreach ($roleModels as $role) {
            $this->assignRoleToOrganization($role, $this->organisation_id);
        }

        // Clear the permissions cache
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Get role models by name
     */
    protected function getRoleByName($roleName)
    {
        $guard = $this->getDefaultGuardName();
        return Role::where('name', $roleName)->where('guard_name', $guard)->first();
    }

    /**
     * Assign a role to the user in a specific organization
     * FIX: Ensure the role parameter is an object with ID, not an array
     */
    protected function assignRoleToOrganization($role, $organizationId)
    {
        // Make sure role is an object with ID property
        if (!is_object($role) || !isset($role->id)) {
            \Log::error('Invalid role object', [
                'role' => $role,
                'type' => gettype($role)
            ]);
            return;
        }

        // Check if already assigned
        $exists = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $this->id)
            ->where('model_type', get_class($this))
            ->where('organisation_id', $organizationId)
            ->exists();

        if (!$exists) {
            DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_id' => $this->id,
                'model_type' => get_class($this),
                'organisation_id' => $organizationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Override getPermissionsViaRoles to include organization context
     */
    public function getPermissionsViaRoles(): Collection
    {
        if (!$this->organisation_id || !config('permission.teams', false)) {
            return $this->spatieGetPermissionsViaRoles();
        }

        // Get role IDs for this user in this organization
        $roleIds = DB::table('model_has_roles')
            ->where('model_id', $this->id)
            ->where('model_type', get_class($this))
            ->where('organisation_id', $this->organisation_id)
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        // Get permissions for these roles
        return DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_has_permissions.role_id', $roleIds)
            ->get(['permissions.id', 'permissions.name', 'permissions.guard_name'])
            ->map(function($permission) {
                return app(\Spatie\Permission\Models\Permission::class)->findById(
                    $permission->id, $permission->guard_name
                );
            });
    }

    /**
     * Get roles for a specific organization
     */
    public function getRolesForOrganization($organizationId): Collection
    {
        return DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.organisation_id', $organizationId)
            ->get(['roles.id', 'roles.name', 'roles.guard_name'])
            ->map(function($role) {
                return app(Role::class)->findById($role->id, $role->guard_name);
            });
    }
}
