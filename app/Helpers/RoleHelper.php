<?php

use App\Models\User;
use App\Services\RoleManager;

if (!function_exists('role_manager')) {
    /**
     * Get the RoleManager instance
     *
     * @return RoleManager
     */
    function role_manager(): RoleManager
    {
        return app(RoleManager::class);
    }
}

if (!function_exists('assign_role')) {
    /**
     * Assign role to user
     *
     * @param User $user
     * @param string $roleName
     * @param int $organisationId
     * @return bool
     */
    function assign_role(User $user, string $roleName, int $organisationId): bool
    {
        return role_manager()->assignRoleToUser($user, $roleName, $organisationId);
    }
}

if (!function_exists('get_default_role')) {
    /**
     * Get default role name
     *
     * @return string
     */
    function get_default_role(): string
    {
        return role_manager()->getDefaultRoleName();
    }
}
