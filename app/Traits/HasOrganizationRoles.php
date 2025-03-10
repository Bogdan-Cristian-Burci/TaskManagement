<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Traits\HasRoles;

trait HasOrganizationRoles
{
    use HasRoles;

    /**
     * Get direct roles attribute
     */
    public function getDirectRolesAttribute(): array
    {
        if (!$this->organisation_id) {
            return [];
        }

        return \DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.organisation_id', $this->organisation_id)
            ->pluck('roles.name')
            ->toArray();
    }

    /**
     * Check if user has a role
     */
    public function hasRole($roles, $guard = null): bool
    {
        if (!$this->organisation_id) {
            return parent::hasRole($roles, $guard);
        }

        // Get role names
        $roleNames = $this->getDirectRolesAttribute();

        // Normalize input roles
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        // Check if any of the roles match
        foreach ($roles as $role) {
            if (in_array($role, $roleNames)) {
                return true;
            }
        }

        return false;
    }
}
