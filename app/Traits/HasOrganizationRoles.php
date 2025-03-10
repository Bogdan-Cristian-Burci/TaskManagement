<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

trait HasOrganizationRoles
{
    use HasRoles {
        roles as traitRoles;
        hasRole as traitHasRole;
        assignRole as traitAssignRole;
    }

    /**
     * Override the roles relationship to include organization_id
     */
    public function roles(): MorphToMany
    {
        $relation = $this->traitRoles();

        // If the model has an organization_id, filter by it
        if ($this->organisation_id) {
            $relation->where('model_has_roles.organisation_id', $this->organisation_id);
        }

        return $relation;
    }

    /**
     * Override hasRole to check in the correct organization context
     */
    public function hasRole($roles, $guard = null): bool
    {
        // If we have an organisation_id and teams is enabled in config
        if ($this->organisation_id && config('permission.teams', false)) {
            // Check with direct query to include organization context
            $roleCount = DB::table('roles')
                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $this->id)
                ->where('model_has_roles.model_type', get_class($this))
                ->where('model_has_roles.organisation_id', $this->organisation_id)
                ->when(is_string($roles), function ($query) use ($roles) {
                    return $query->where('roles.name', $roles);
                })
                ->when(is_array($roles), function ($query) use ($roles) {
                    return $query->whereIn('roles.name', $roles);
                })
                ->count();

            return $roleCount > 0;
        }

        // Otherwise use the trait method
        return $this->traitHasRole($roles, $guard);
    }

    /**
     * Override assignRole to include organization_id
     */
    public function assignRole(...$roles)
    {
        // Get organisation_id from the model
        $organisationId = $this->organisation_id;

        if (!$organisationId) {
            return $this->traitAssignRole(...$roles);
        }

        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                return $this->getStoredRole($role);
            })
            ->each(function ($role) use ($organisationId) {
                $this->ensureModelHasRole($role, $organisationId);
            });

        return $this;
    }

    /**
     * Custom method to assign role with explicit organization
     */
    public function ensureModelHasRole($role, $organisationId): void
    {
        // Check if role is already assigned in this organization
        $exists = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $this->id)
            ->where('model_type', get_class($this))
            ->where('organisation_id', $organisationId)
            ->exists();

        if (!$exists) {
            DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_id' => $this->id,
                'model_type' => get_class($this),
                'organisation_id' => $organisationId,
            ]);
        }
    }

    /**
     * A helpful method to know what roles the user has in a specific organization
     */
    public function getRolesInOrganization($organisationId): Collection
    {
        return DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.organisation_id', $organisationId)
            ->get(['roles.id', 'roles.name', 'roles.guard_name']);
    }
}
