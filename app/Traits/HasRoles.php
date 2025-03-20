<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\RoleTemplate;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasRoles
{
    use OrganisationHelpers;

    /**
     * Get the roles that the user has.
     *
     * @return BelongsToMany
     */
    public function roles() : BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')
            ->where('model_type', static::class)
            ->withPivot('organisation_id')
            ->withTimestamps();
    }

    /**
     * Check if user has a specific role in the specified organization.
     *
     * @param string|int|Role $role Role template name, Role ID, or Role object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasRole(mixed $role, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        $query = $this->roles()
            ->where('model_has_roles.organisation_id', $organisationId);

        if (is_string($role)) {
            // Role template name
            return $query->whereHas('template', function($q) use ($role) {
                $q->where('name', $role);
            })->exists();
        }

        if (is_int($role)) {
            // Role ID
            return $query->where('roles.id', $role)->exists();
        }

        if ($role instanceof Role) {
            // Role object
            return $query->where('roles.id', $role->id)->exists();
        }

        return false;
    }

    /**
     * Check if user has any of the specified roles.
     *
     * @param array $roles Array of role template names, Role IDs, or Role objects
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
     * @param array $roles Array of role template names, Role IDs, or Role objects
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
     * @param string|int|Role $role Role template name, Role ID, or Role object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function attachRole(mixed $role, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        // Get role ID
        $roleId = null;

        if ($role instanceof Role) {
            $roleId = $role->id;
        } elseif (is_int($role)) {
            // Direct role ID
            $roleId = $role;
        } elseif (is_string($role)) {
            // Role template name - find or create the role for this org
            $template = RoleTemplate::getTemplateByName($role, $organisationId);
            if (!$template) {
                return false;
            }

            // Find or create the role for this template in this org
            $roleModel = Role::where('template_id', $template->id)
                ->where('organisation_id', $organisationId)
                ->first();

            if (!$roleModel) {
                $roleModel = $template->createRoleInOrganisation(
                    Organisation::find($organisationId)
                );
            }

            $roleId = $roleModel->id;
        }

        if (!$roleId) {
            return false;
        }

        // Check if already assigned in this org
        $exists = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('organisation_id', $organisationId)
            ->exists();

        if (!$exists) {
            // Insert into model_has_roles
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_id' => $this->id,
                'model_type' => static::class,
                'organisation_id' => $organisationId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return true;
        }

        return false;
    }

    /**
     * Detach a role from user.
     *
     * @param string|int|Role $role Role template name, Role ID, or Role object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function detachRole(mixed $role, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        $query = DB::table('model_has_roles')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('organisation_id', $organisationId);

        if ($role instanceof Role) {
            $query->where('role_id', $role->id);
        } elseif (is_int($role)) {
            $query->where('role_id', $role);
        } elseif (is_string($role)) {
            // Role template name - find roles with this template
            $templateIds = RoleTemplate::where('name', $role)
                ->pluck('id')
                ->toArray();

            if (empty($templateIds)) {
                return false;
            }

            $roleIds = Role::whereIn('template_id', $templateIds)
                ->where('organisation_id', $organisationId)
                ->pluck('id')
                ->toArray();

            if (empty($roleIds)) {
                return false;
            }

            $query->whereIn('role_id', $roleIds);
        } else {
            return false;
        }

        $affected = $query->delete();
        return $affected > 0;
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
        if (!$organisationId) {
            return collect();
        }

        return $this->roles()
            ->where('model_has_roles.organisation_id', $organisationId)
            ->with('template')
            ->get();
    }

    /**
     * Check if the user has a role in a specific organization.
     * Alias for hasRole for backwards compatibility.
     *
     * @param mixed $role Role template name, Role ID, or Role object
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function hasRoleInOrganisation(mixed $role, int|Organisation $organisation = null): bool
    {
        return $this->hasRole($role, $organisation);
    }

    /**
     * Sync roles for the user in an organization.
     *
     * @param array $roles Array of role template names, Role IDs, or Role objects
     * @param int|Organisation|null $organisation Organisation context
     * @return bool
     */
    public function syncRoles(array $roles, int|Organisation $organisation = null): bool
    {
        $organisationId = $this->getOrganisationId($organisation);
        if (!$organisationId) {
            return false;
        }

        // Remove all current roles in this org
        DB::table('model_has_roles')
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->where('organisation_id', $organisationId)
            ->delete();

        // Add new roles
        foreach ($roles as $role) {
            $this->attachRole($role, $organisationId);
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
        if (!$organisationId) {
            return null;
        }

        return $this->roles()
            ->where('model_has_roles.organisation_id', $organisationId)
            ->with('template')
            ->get()
            ->sortByDesc(function($role) {
                return $role->getLevel();
            })
            ->first();
    }
}
