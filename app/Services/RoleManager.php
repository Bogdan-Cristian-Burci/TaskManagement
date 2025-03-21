<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Role Manager Service
 *
 * Single source of truth for role and permission management throughout the application.
 * This service reads from config files and ensures database consistency.
 */
class RoleManager
{
    /**
     * Get all defined permissions from configuration
     *
     * @return array
     */
    public function getAllDefinedPermissions(): array
    {
        $permissions = [];

        // Standard permissions (model.action)
        $models = config('permissions.models', []);
        $standardActions = config('permissions.standard_actions', []);

        foreach ($models as $model) {
            foreach ($standardActions as $action) {
                $permissions[] = "{$model}.{$action}";
            }
        }

        // Extended permissions
        $extendedActions = config('permissions.extended_actions', []);
        foreach ($extendedActions as $model => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$model}.{$action}";
            }
        }

        // Custom permissions
        foreach (config('permissions.custom', []) as $permission) {
            $permissions[] = $permission;
        }

        // If no permissions are defined in config, use fallback defaults
        if (empty($permissions)) {
            // Basic CRUD permissions for common entities
            $basicModels = ['user', 'project', 'task', 'organisation'];
            $basicActions = ['viewAny', 'view', 'create', 'update', 'delete'];

            foreach ($basicModels as $model) {
                foreach ($basicActions as $action) {
                    $permissions[] = "{$model}.{$action}";
                }
            }

            // Add critical permissions
            $permissions[] = 'manage-roles';
            $permissions[] = 'manage-permissions';
        }

        return $permissions;
    }

    /**
     * Get all defined role templates from configuration
     *
     * @return array
     */
    public function getAllDefinedRoles(): array
    {
        $roles = [];

        // System roles
        foreach (config('roles.system', []) as $name => $role) {
            $roles[$name] = array_merge($role, ['scope' => 'system']);
        }

        // Organization roles
        foreach (config('roles.organization', []) as $name => $role) {
            $roles[$name] = array_merge($role, ['scope' => 'organization']);
        }

        // If no roles are defined in config, use fallback defaults
        if (empty($roles)) {
            $roles = $this->getFallbackRoles();
        }

        return $roles;
    }

    /**
     * Get fallback roles when config is missing
     *
     * @return array
     */
    private function getFallbackRoles(): array
    {
        return [
            'admin' => [
                'display_name' => 'Administrator',
                'description' => 'Full administrative access',
                'level' => 100,
                'permissions' => 'all',
                'is_system' => true,
                'can_be_deleted' => false,
                'scope' => 'organization',
            ],
            'member' => [
                'display_name' => 'Member',
                'description' => 'Regular organization member',
                'level' => 40,
                'permissions' => [
                    'user.viewAny', 'user.view',
                    'project.viewAny', 'project.view',
                    'task.viewAny', 'task.view', 'task.create',
                    'team.viewAny', 'team.view',
                ],
                'is_system' => true,
                'can_be_deleted' => false,
                'scope' => 'organization',
            ],
        ];
    }

    /**
     * Get permissions for a specific role
     *
     * @param string $roleName
     * @param string $scope
     * @return array
     */
    public function getPermissionsForRole(string $roleName, string $scope = 'organization'): array
    {
        // First check in specific scope
        if (config("roles.{$scope}.{$roleName}.permissions") !== null) {
            $permissions = config("roles.{$scope}.{$roleName}.permissions");

            // If 'all' is specified, return all defined permissions
            if ($permissions === 'all') {
                return $this->getAllDefinedPermissions();
            }

            return $permissions;
        }

        // Check in other scopes if not found
        foreach (['system', 'organization'] as $s) {
            if ($s === $scope) continue; // Already checked

            if (config("roles.{$s}.{$roleName}.permissions") !== null) {
                $permissions = config("roles.{$s}.{$roleName}.permissions");

                // If 'all' is specified, return all defined permissions
                if ($permissions === 'all') {
                    return $this->getAllDefinedPermissions();
                }

                return $permissions;
            }
        }

        // Use fallback permissions if role not found
        $fallbackRoles = $this->getFallbackRoles();
        if (isset($fallbackRoles[$roleName]['permissions'])) {
            $permissions = $fallbackRoles[$roleName]['permissions'];

            // If 'all' is specified, return all defined permissions
            if ($permissions === 'all') {
                return $this->getAllDefinedPermissions();
            }

            return $permissions;
        }

        // Return empty array if role not found
        return [];
    }

    /**
     * Get role info from configuration
     *
     * @param string $roleName
     * @param string $scope
     * @return array|null
     */
    public function getRoleInfo(string $roleName, string $scope = 'organization'): ?array
    {
        // Check in specific scope first
        if (config("roles.{$scope}.{$roleName}") !== null) {
            $role = config("roles.{$scope}.{$roleName}");
            return array_merge($role, ['name' => $roleName, 'scope' => $scope]);
        }

        // Check in other scopes if not found
        foreach (['system', 'organization'] as $s) {
            if ($s === $scope) continue; // Already checked

            if (config("roles.{$s}.{$roleName}") !== null) {
                $role = config("roles.{$s}.{$roleName}");
                return array_merge($role, ['name' => $roleName, 'scope' => $s]);
            }
        }

        // Check fallback roles
        $fallbackRoles = $this->getFallbackRoles();
        if (isset($fallbackRoles[$roleName])) {
            return $fallbackRoles[$roleName];
        }

        return null;
    }

    /**
     * Get the default role name
     *
     * @return string
     */
    public function getDefaultRoleName(): string
    {
        return config('roles.default', 'member');
    }

    /**
     * Check if a role exists in configuration
     *
     * @param string $roleName
     * @return bool
     */
    public function roleExists(string $roleName): bool
    {
        foreach (['system', 'organization'] as $scope) {
            if (config("roles.{$scope}.{$roleName}") !== null) {
                return true;
            }
        }

        // Check fallback roles
        $fallbackRoles = $this->getFallbackRoles();
        if (isset($fallbackRoles[$roleName])) {
            return true;
        }

        return false;
    }

    /**
     * Validate and normalize a role name
     *
     * @param string $roleName
     * @return string
     */
    public function validateRoleName(string $roleName): string
    {
        if ($this->roleExists($roleName)) {
            return $roleName;
        }

        return $this->getDefaultRoleName();
    }

    /**
     * Assign a role to a user in an organization
     *
     * @param User $user
     * @param string $roleName
     * @param int $organisationId
     * @return bool
     */
    public function assignRoleToUser(User $user, string $roleName, int $organisationId): bool
    {
        try {
            // Validate role name
            $roleName = $this->validateRoleName($roleName);

            // Get role info from config
            $roleInfo = $this->getRoleInfo($roleName);
            if (!$roleInfo) {
                Log::error("Role {$roleName} not found in configuration");
                return false;
            }

            // Get or create the template
            $template = RoleTemplate::firstOrCreate(
                [
                    'name' => $roleName,
                    'is_system' => true,
                    'scope' => $roleInfo['scope'] ?? 'organization',
                ],
                [
                    'display_name' => $roleInfo['display_name'],
                    'description' => $roleInfo['description'],
                    'level' => $roleInfo['level'],
                ]
            );

            // Create or get the role based on the template
            $role = Role::firstOrCreate(
                [
                    'template_id' => $template->id,
                    'organisation_id' => $organisationId,
                ],
                [
                    'overrides_system' => false,
                    'system_role_id' => null
                ]
            );

            // Assign the role to the user
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $role->id,
                    'model_id' => $user->id,
                    'model_type' => get_class($user),
                    'organisation_id' => $organisationId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Sync permissions to the template
            $this->syncTemplatePermissions($template);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to assign role {$roleName} to user {$user->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Sync template permissions from configuration
     *
     * @param RoleTemplate $template
     * @return void
     */
    public function syncTemplatePermissions(RoleTemplate $template): void
    {
        // Get permission names for this template from config
        $permissionNames = $this->getPermissionsForRole(
            $template->name,
            $template->scope ?? 'organization'
        );

        // Get permission IDs
        $permissionIds = Permission::whereIn('name', $permissionNames)
            ->pluck('id')
            ->toArray();

        // Sync permissions to template
        $template->permissions()->sync($permissionIds);
    }

    /**
     * Create or update all permissions from configuration
     *
     * @return int Number of permissions processed
     */
    public function syncAllPermissions(): int
    {
        $count = 0;

        // Get all defined permissions
        $definedPermissions = $this->getAllDefinedPermissions();

        // Process all permissions
        foreach ($definedPermissions as $name) {
            // Determine model and action from permission name
            $parts = explode('.', $name);

            if (count($parts) === 2) {
                // Standard model.action permission
                $model = $parts[0];
                $action = $parts[1];
                $displayName = ucfirst($action) . ' ' . ucfirst($model);
                $description = "Allows user to {$action} {$model}s";
                $category = ucfirst($model);
            } else {
                // Custom permission
                $displayName = ucwords(str_replace('-', ' ', $name));
                $description = "Allows user to {$displayName}";
                $category = 'General';
            }

            $this->createOrUpdatePermission($name, $displayName, $description, $category);
            $count++;
        }

        return $count;
    }

    /**
     * Create or update a permission
     *
     * @param string $name
     * @param string $displayName
     * @param string $description
     * @param string $category
     * @return void
     */
    private function createOrUpdatePermission(
        string $name,
        string $displayName,
        string $description,
        string $category
    ): void
    {
        Permission::updateOrCreate(
            ['name' => $name],
            [
                'display_name' => $displayName,
                'description' => $description,
                'category' => $category,
                'guard_name' => 'api'
            ]
        );
    }

    /**
     * Create or update all role templates from configuration
     *
     * @return int Number of templates processed
     */
    public function syncAllRoleTemplates(): int
    {
        $count = 0;
        $roles = $this->getAllDefinedRoles();

        foreach ($roles as $name => $role) {
            $this->createOrUpdateTemplate($name, $role, $role['scope'] ?? 'organization');
            $count++;
        }

        return $count;
    }

    /**
     * Create or update a role template
     *
     * @param string $name
     * @param array $data
     * @param string $scope
     * @return void
     */
    private function createOrUpdateTemplate(string $name, array $data, string $scope): void
    {
        $template = RoleTemplate::updateOrCreate(
            [
                'name' => $name,
                'is_system' => true,
                'scope' => $scope,
            ],
            [
                'display_name' => $data['display_name'],
                'description' => $data['description'],
                'level' => $data['level'],
                'can_be_deleted' => $data['can_be_deleted'] ?? false,
            ]
        );

        // Sync permissions
        $this->syncTemplatePermissions($template);

    }

    /**
     * Create roles for all organizations based on templates
     *
     * @return int Number of organizations processed
     */
    public function syncOrganizationRoles(): int
    {
        $organizations = Organisation::all();
        $count = 0;

        foreach ($organizations as $organization) {
            $this->createOrganizationRoles($organization->id);
            $count++;
        }

        return $count;
    }

    /**
     * Create roles for an organization based on templates
     *
     * @param int $organisationId
     * @return int Number of roles created
     */
    public function createOrganizationRoles(int $organisationId): int
    {
        $count = 0;
        $organisation = Organisation::find($organisationId);

        if (!$organisation) {
            return 0;
        }

        // Get all organization roles from config
        $orgRoles = config('roles.organization', []);

        // Create a role for each organization role in config
        foreach ($orgRoles as $roleName => $roleData) {
            // Check if the role template exists
            $template = RoleTemplate::where('name', $roleName)
                ->where('is_system', true)
                ->first();

            // If template doesn't exist, create it
            if (!$template) {
                $template = RoleTemplate::create([
                    'name' => $roleName,
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description'],
                    'level' => $roleData['level'],
                    'is_system' => true,
                    'can_be_deleted' => $roleData['can_be_deleted'] ?? false,
                    'scope' => 'organization',
                ]);

                // Sync template permissions
                $this->syncTemplatePermissions($template);
            }

            // Check if organization role exists
            $exists = Role::where('template_id', $template->id)
                ->where('organisation_id', $organisationId)
                ->exists();

            // Create the role if it doesn't exist
            if (!$exists) {
                Role::create([
                    'template_id' => $template->id,
                    'organisation_id' => $organisationId,
                    'overrides_system' => false,
                    'system_role_id' => null,
                ]);
                $count++;
            }
        }

        return $count;
    }
}
