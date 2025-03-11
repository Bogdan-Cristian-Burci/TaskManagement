<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Organisation;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Clear cache before running seeds
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Starting permission and role seeding...');

        // Define models that need permissions
        $models = [
            'project',
            'task',
            'user',
            'organisation',
            'board',
            'status',
            'priority',
            'taskType',
            'comment',
            'attachment',
            'notification',
            'team',
            'role',         // Add role model explicitly
            'permission',   // Add permission model explicitly
        ];

        // Define standard actions for each model
        $standardActions = [
            'viewAny',   // index method
            'view',      // show method
            'create',    // create/store methods
            'update',    // edit/update methods
            'delete',    // destroy method
            'forceDelete', // forceDelete method
            'restore',   // restore method
        ];

        // Extended actions for specific models
        $extendedActions = [
            'project' => [
                'addMember',
                'removeMember',
                'changeOwner',
            ],
            'task' => [
                'assign',
                'changeStatus',
                'changePriority',
                'addLabel',
                'removeLabel',
                'moveTask',
                'attachFile',
                'detachFile',
            ],
            'organisation' => [
                'inviteUser',
                'removeUser',
                'assignRole',
                'viewMetrics',
                'manageSettings',
                'exportData',
            ],
            'board' => [
                'reorderColumns',
                'addColumn',
                'removeColumn',
                'changeColumnSettings',
            ],
            'role' => [
                'assign',
                'revoke',
                'manage',
            ],
            'permission' => [
                'assign',
                'revoke',
                'manage',
            ],
            'team' => [
                'addMember',
                'removeMember',
                'changeLead',
            ],
        ];

        // Define custom permissions that don't follow the model.action pattern
        $customPermissions = [
            'manage roles',          // For backward compatibility
            'manage permissions',
            'manage users',
            'manage organisations',
            'manage teams',
            'manage projects',
            'manage settings',
        ];

        // Initialize permission collections by role
        $permissionsByRole = [
            'admin' => [],       // Organization admin - gets all permissions
            'member' => [],      // Regular team member - gets limited permissions
        ];

        // Create standard permissions for all models
        foreach ($models as $model) {
            foreach ($standardActions as $action) {
                $permissionName = "{$model}.{$action}";
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'api'
                ]);

                // Add to admin permissions - admin gets all permissions
                $permissionsByRole['admin'][] = $permissionName;

                // Member role gets view/viewAny permissions for most resources
                if (in_array($action, ['view', 'viewAny'])) {
                    // Members can view most resources except role/permission management
                    if (!in_array($model, ['role', 'permission'])) {
                        $permissionsByRole['member'][] = $permissionName;
                    }
                }

                // Member role gets create/update/delete permissions for specific resources
                if (in_array($action, ['create', 'update', 'delete']) &&
                    in_array($model, ['task', 'comment', 'attachment'])) {
                    $permissionsByRole['member'][] = $permissionName;
                }
            }

            // Add model-specific extended actions
            if (isset($extendedActions[$model])) {
                foreach ($extendedActions[$model] as $extendedAction) {
                    $permissionName = "{$model}.{$extendedAction}";
                    $permission = Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'api'
                    ]);

                    // Add to admin permissions
                    $permissionsByRole['admin'][] = $permissionName;

                    // Add specific permissions to member role
                    if (in_array($model, ['task', 'comment', 'attachment']) &&
                        in_array($extendedAction, [
                            'assign', 'changeStatus', 'changePriority',
                            'attachFile', 'detachFile', 'addLabel', 'removeLabel'
                        ])) {
                        $permissionsByRole['member'][] = $permissionName;
                    }
                }
            }
        }

        // Create custom permissions
        foreach ($customPermissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api'
            ]);

            // Only admins get these custom permissions
            $permissionsByRole['admin'][] = $permissionName;
        }

        // Create standard roles
        $roles = [
            'admin' => 'Organization Administrator',
            'member' => 'Team Member',
        ];

        // Role levels (higher number = higher privilege)
        $roleLevels = [
            'admin' => 80,
            'member' => 40,
        ];

        // Create organization-specific roles
        $this->createOrganizationRoles($roles, $roleLevels, $permissionsByRole);

        $this->command->info('Permissions and roles seeded successfully!');
    }

    /**
     * Create organization-specific roles for all organizations
     *
     * @param array $roles
     * @param array $roleLevels
     * @param array $permissionsByRole
     */
    public function createOrganizationRoles(array $roles, array $roleLevels, array $permissionsByRole): void
    {
        // Get all organizations
        $organizations = Organisation::all();
        $this->command->info("Creating organization-specific roles for " . $organizations->count() . " organizations");

        foreach ($organizations as $organization) {
            $this->createOrganizationRolesForOrg(
                $organization->id,
                $organization->owner_id,
                $roles,
                $roleLevels,
                $permissionsByRole
            );
        }
    }

    /**
     * Create roles and assign permissions for a specific organization
     *
     * @param int $organizationId
     * @param int $ownerId
     * @param array $roles
     * @param array $roleLevels
     * @param array $permissionsByRole
     * @return void
     */
    public function createOrganizationRolesForOrg(
        int   $organizationId,
        int   $ownerId,
        array $roles,
        array $roleLevels,
        array $permissionsByRole
    ): void
    {
        // Create roles for this organization
        foreach ($roles as $roleName => $roleDescription) {
            // Check if role already exists for this organization
            $role = Role::where('name', $roleName)
                ->where('organisation_id', $organizationId)
                ->first();

            if (!$role) {
                $role = Role::create([
                    'name' => $roleName,
                    'guard_name' => 'api',
                    'organisation_id' => $organizationId,
                    'description' => $roleDescription,
                    'level' => $roleLevels[$roleName],
                ]);
            }

            // Ensure all permissions exist first
            foreach ($permissionsByRole[$roleName] as $permissionName) {
                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'api'
                ]);
            }

            // Sync permissions to the role
            $role->syncPermissions($permissionsByRole[$roleName]);

            // If this is the admin role, assign it to the owner
            if ($roleName === 'admin' && $ownerId) {
                $owner = User::find($ownerId);
                if ($owner) {
                    // Clear cache
                    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

                    // Remove existing roles for this user in this organization
                    $existingRoles = DB::table('model_has_roles')
                        ->where('model_id', $owner->id)
                        ->where('model_type', get_class($owner))
                        ->where('organisation_id', $organizationId)
                        ->delete();

                    // Assign admin role to owner
                    DB::table('model_has_roles')->insert([
                        'role_id' => $role->id,
                        'model_id' => $owner->id,
                        'model_type' => get_class($owner),
                        'organisation_id' => $organizationId,
                    ]);

                    $this->command->info("User ID {$ownerId} has been assigned the admin role for organisation {$organizationId}");
                }
            }

            $this->command->info("Created role {$roleName} for organisation {$organizationId} with " .
                count($permissionsByRole[$roleName]) . " permissions");
        }
    }

    // Create a public method that can be called elsewhere in the codebase
    // to ensure an admin user has the required permissions
    public static function addMissingPermissionsToAdmin($userId, $organizationId): bool
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Find the user
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // Find the admin role for this organization
        $adminRole = Role::where('name', 'admin')
            ->where('organisation_id', $organizationId)
            ->first();

        if (!$adminRole) {
            return false;
        }

        // Ensure the user has the admin role
        $hasRole = DB::table('model_has_roles')
            ->where('role_id', $adminRole->id)
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->where('organisation_id', $organizationId)
            ->exists();

        if (!$hasRole) {
            // Assign admin role to the user
            DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id,
                'model_id' => $user->id,
                'model_type' => get_class($user),
                'organisation_id' => $organizationId,
            ]);
        }

        // Ensure critical permissions exist
        $criticalPermissions = [
            'role.view',
            'role.viewAny',
            'permission.view',
            'permission.viewAny',
            'manage roles',
        ];

        foreach ($criticalPermissions as $permissionName) {
            // Make sure permission exists
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api'
            ]);

            // Make sure role has permission
            if (!$adminRole->hasPermissionTo($permissionName)) {
                $adminRole->givePermissionTo($permissionName);
            }
        }

        return true;
    }
}
