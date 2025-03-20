<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Organisation;
use App\Models\RoleTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasPermissions
{
    use OrganisationHelpers;

    /**
     * Check if user has a specific permission in the specified organization.
     *
     * @param string|int|Permission $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasPermission(mixed $permission, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        // Get permission ID or name
        $permissionName = null;
        $permissionId = null;

        if ($permission instanceof Permission) {
            $permissionId = $permission->id;
            $permissionName = $permission->name;
        } elseif (is_int($permission)) {
            $permissionId = $permission;
            $permissionObj = Permission::find($permissionId);
            if ($permissionObj) {
                $permissionName = $permissionObj->name;
            } else {
                return false;
            }
        } elseif (is_string($permission)) {
            $permissionName = $permission;
            $permissionObj = Permission::where('name', $permissionName)->first();
            if ($permissionObj) {
                $permissionId = $permissionObj->id;
            } else {
                return false;
            }
        } else {
            return false;
        }

        // First check for direct permission overrides (denials)
        $denied = DB::table('model_has_permissions')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('permission_id', $permissionId)
            ->where('organisation_id', $organisationId)
            ->where('grant', false)
            ->exists();

        if ($denied) {
            return false;
        }

        // Then check for direct permission overrides (grants)
        $granted = DB::table('model_has_permissions')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('permission_id', $permissionId)
            ->where('organisation_id', $organisationId)
            ->where('grant', true)
            ->exists();

        if ($granted) {
            return true;
        }

        // Check permissions through roles/templates
        return DB::table('permissions')
            ->join('template_has_permissions', 'permissions.id', '=', 'template_has_permissions.permission_id')
            ->join('role_templates', 'template_has_permissions.role_template_id', '=', 'role_templates.id')
            ->join('roles', 'role_templates.id', '=', 'roles.template_id')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('permissions.name', $permissionName)
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', static::class)
            ->where('model_has_roles.organisation_id', $organisationId)
            ->exists();
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
     * @param string|int|Permission $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function grantPermission(mixed $permission, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        // Get permission ID
        $permissionId = null;

        if ($permission instanceof Permission) {
            $permissionId = $permission->id;
        } elseif (is_int($permission)) {
            $permissionId = $permission;
        } elseif (is_string($permission)) {
            $permissionObj = Permission::where('name', $permission)->first();
            if ($permissionObj) {
                $permissionId = $permissionObj->id;
            } else {
                return false;
            }
        } else {
            return false;
        }

        // Remove any existing permission override
        DB::table('model_has_permissions')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('permission_id', $permissionId)
            ->where('organisation_id', $organisationId)
            ->delete();

        // Add grant permission
        DB::table('model_has_permissions')->insert([
            'model_id' => $this->id,
            'model_type' => static::class,
            'permission_id' => $permissionId,
            'organisation_id' => $organisationId,
            'grant' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return true;
    }

    /**
     * Deny a permission to user in specified organization.
     *
     * @param string|int|Permission $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function denyPermission($permission, $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        // Get permission ID
        $permissionId = null;

        if ($permission instanceof Permission) {
            $permissionId = $permission->id;
        } elseif (is_int($permission)) {
            $permissionId = $permission;
        } elseif (is_string($permission)) {
            $permissionObj = Permission::where('name', $permission)->first();
            if ($permissionObj) {
                $permissionId = $permissionObj->id;
            } else {
                return false;
            }
        } else {
            return false;
        }

        // Remove any existing permission override
        DB::table('model_has_permissions')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('permission_id', $permissionId)
            ->where('organisation_id', $organisationId)
            ->delete();

        // Add deny permission
        DB::table('model_has_permissions')->insert([
            'model_id' => $this->id,
            'model_type' => static::class,
            'permission_id' => $permissionId,
            'organisation_id' => $organisationId,
            'grant' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return true;
    }

    /**
     * Remove a direct permission from user.
     *
     * @param string|int|Permission $permission Permission name, ID, or object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function removePermission($permission, $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        // Get permission ID
        $permissionId = null;

        if ($permission instanceof Permission) {
            $permissionId = $permission->id;
        } elseif (is_int($permission)) {
            $permissionId = $permission;
        } elseif (is_string($permission)) {
            $permissionObj = Permission::where('name', $permission)->first();
            if ($permissionObj) {
                $permissionId = $permissionObj->id;
            } else {
                return false;
            }
        } else {
            return false;
        }

        // Remove any permission override
        $affected = DB::table('model_has_permissions')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('permission_id', $permissionId)
            ->where('organisation_id', $organisationId)
            ->delete();

        return $affected > 0;
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
        if (!$organisationId) {
            return collect();
        }

        // Get denied permissions
        $deniedIds = DB::table('model_has_permissions')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('organisation_id', $organisationId)
            ->where('grant', false)
            ->pluck('permission_id')
            ->toArray();

        // Get direct granted permissions
        $directPermissions = Permission::whereIn('id', function($query) use ($organisationId) {
            $query->select('permission_id')
                ->from('model_has_permissions')
                ->where('model_id', $this->id)
                ->where('model_type', static::class)
                ->where('organisation_id', $organisationId)
                ->where('grant', true);
        })->get();

        // Get role permissions through templates
        $rolePermissionsQuery = Permission::whereIn('id', function($query) use ($organisationId) {
            $query->select('permission_id')
                ->from('template_has_permissions')
                ->whereIn('role_template_id', function($q) use ($organisationId) {
                    $q->select('template_id')
                        ->from('roles')
                        ->whereIn('id', function($r) use ($organisationId) {
                            $r->select('role_id')
                                ->from('model_has_roles')
                                ->where('model_id', $this->id)
                                ->where('model_type', static::class)
                                ->where('organisation_id', $organisationId);
                        });
                });
        });

        // Exclude denied permissions
        if (!empty($deniedIds)) {
            $rolePermissionsQuery->whereNotIn('id', $deniedIds);
        }

        $rolePermissions = $rolePermissionsQuery->get();

        // Merge both sets and return unique permissions
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
     * Backwards compatibility - alias for grantPermission/denyPermission
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

        $granted = DB::table('model_has_permissions')
            ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_id', $this->id)
            ->where('model_has_permissions.model_type', static::class)
            ->where('model_has_permissions.organisation_id', $this->organisation_id)
            ->where('model_has_permissions.grant', true)
            ->pluck('permissions.name')
            ->toArray();

        $denied = DB::table('model_has_permissions')
            ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_id', $this->id)
            ->where('model_has_permissions.model_type', static::class)
            ->where('model_has_permissions.organisation_id', $this->organisation_id)
            ->where('model_has_permissions.grant', false)
            ->pluck('permissions.name')
            ->toArray();

        return [
            'grant' => $granted,
            'deny' => $denied
        ];
    }
}
