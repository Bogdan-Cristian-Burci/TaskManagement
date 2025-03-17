<?php

namespace App\Traits;

use App\Models\Organisation;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
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
     * Check if user has permission in organization context
     * This method now checks both direct permissions and permissions from templates
     *
     * @param string $permission
     * @param Organisation|int $organisation
     * @return bool
     * @throws BindingResolutionException
     */
    public function hasOrganisationPermission($permission, $organisation): bool
    {
        $authService = App::make(AuthorizationService::class);

        // If permission is an array, check each one
        if (is_array($permission)) {
            foreach ($permission as $perm) {
                if ($authService->hasOrganisationPermission($this, $perm, $organisation)) {
                    return true;
                }
            }
            return false;
        }

        // For a single permission, delegate to the service
        return $authService->hasOrganisationPermission($this, $permission, $organisation);
    }

    /**
     * Add a permission override for this user
     *
     * @param string $permission Permission name
     * @param int|Organisation $organisation Organisation
     * @param string $type Whether to grant or deny ('grant' or 'deny')
     * @return User|HasOrganizationPermissions
     */
    public function addPermissionOverride(string $permission, int|Organisation $organisation, string $type = 'grant'): self
    {
        $organisationId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        // Find the permission
        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->value('id');

        if (!$permissionId) {
            // Create the permission if it doesn't exist
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $permission,
                'guard_name' => 'api',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Check if override already exists
        $exists = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_id', $this->id)
            ->where('model_type', get_class($this))
            ->where('organisation_id', $organisationId)
            ->exists();

        if ($exists) {
            // Update existing override
            DB::table('model_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('model_id', $this->id)
                ->where('model_type', get_class($this))
                ->where('organisation_id', $organisationId)
                ->update(['type' => $type]);
        } else {
            // Create new override
            DB::table('model_has_permissions')->insert([
                'permission_id' => $permissionId,
                'model_id' => $this->id,
                'model_type' => get_class($this),
                'organisation_id' => $organisationId,
                'type' => $type
            ]);
        }

        return $this;
    }

    /**
     * Remove a permission override for this user
     */
    public function removePermissionOverride(string $permission, $organisation): self
    {
        $organisationId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        // Find the permission
        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->value('id');

        if ($permissionId) {
            // Remove the override
            DB::table('model_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('model_id', $this->id)
                ->where('model_type', get_class($this))
                ->where('organisation_id', $organisationId)
                ->delete();
        }

        return $this;
    }

    /**
     * Get all permissions for this user in the current organization context
     *
     * @return array
     */
    public function getOrganisationPermissionsAttribute(): array
    {
        if (!$this->organisation_id) {
            return [];
        }

        $authService = app(AuthorizationService::class);
        return $authService->getEffectivePermissions($this, $this->organisation_id);
    }
}
