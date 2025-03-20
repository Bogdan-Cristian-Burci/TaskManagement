<?php

namespace App\Traits;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTemplate;
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
     * @param mixed $role Role template name, Role ID, or Role object
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
            // First try to find a role with this template name in the organization
            $roleModel = Role::whereHas('template', function($query) use ($role) {
                $query->where('name', $role);
            })
                ->where('organisation_id', $organisationId)
                ->first();

            if ($roleModel) {
                return $roleModel->id;
            }

            // If not found, try to create a role from a template with this name
            $template = RoleTemplate::getTemplateByName($role, $organisationId);
            if ($template) {
                $organisation = Organisation::find($organisationId);
                if ($organisation) {
                    $roleModel = $template->createRoleInOrganisation($organisation);
                    return $roleModel->id;
                }
            }
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

    /**
     * Helper method to get role template ID.
     *
     * @param mixed $template Template name, Template ID, or Template object
     * @param int|null $organisationId
     * @return int|null
     */
    protected function getRoleTemplateId(mixed $template, ?int $organisationId = null): ?int
    {
        if (is_int($template)) {
            return $template;
        }

        if ($template instanceof RoleTemplate) {
            return $template->id;
        }

        if (is_string($template)) {
            $templateModel = RoleTemplate::getTemplateByName($template, $organisationId);
            return $templateModel ? $templateModel->id : null;
        }

        return null;
    }

    /**
     * Add role template constraint to a query.
     *
     * @param Builder $query
     * @param mixed $template
     * @return void
     */
    protected function addRoleTemplateConstraint(Builder $query, mixed $template): void
    {
        if (is_string($template)) {
            $query->where('role_templates.name', $template);
        } elseif (is_int($template)) {
            $query->where('role_templates.id', $template);
        } elseif ($template instanceof RoleTemplate) {
            $query->where('role_templates.id', $template->id);
        }
    }
}
