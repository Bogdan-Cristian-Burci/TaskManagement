<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear cache before running seeds
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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
        ];

        $permissionsByRole = [
            'super-admin' => [], // Will get all permissions
            'admin' => [],       // Organization admin
            'manager' => [],     // Project manager
            'member' => [],      // Regular team member
            'guest' => [],       // Limited view-only access
        ];

        // Create standard permissions for all models
        foreach ($models as $model) {
            foreach ($standardActions as $action) {
                $permissionName = "{$model}.{$action}";
                $permission = Permission::findOrCreate($permissionName);

                // Add to admin permissions
                $permissionsByRole['admin'][] = $permissionName;

                // Add view/viewAny permissions to member role
                if (in_array($action, ['view', 'viewAny'])) {
                    $permissionsByRole['member'][] = $permissionName;
                    $permissionsByRole['guest'][] = $permissionName;
                }

                // Add create/update permissions to member role
                if (in_array($action, ['create', 'update'])) {
                    $permissionsByRole['member'][] = $permissionName;
                }

                // Add all standard permissions to manager
                $permissionsByRole['manager'][] = $permissionName;
            }

            // Add model-specific extended actions
            if (isset($extendedActions[$model])) {
                foreach ($extendedActions[$model] as $extendedAction) {
                    $permissionName = "{$model}.{$extendedAction}";
                    $permission = Permission::findOrCreate($permissionName);

                    // Add to admin permissions
                    $permissionsByRole['admin'][] = $permissionName;

                    // Add to manager permissions
                    $permissionsByRole['manager'][] = $permissionName;

                    // Add specific permissions to member role
                    if (in_array($extendedAction, [
                        'assign', 'changeStatus', 'changePriority',
                        'attachFile', 'detachFile', 'addLabel', 'removeLabel'
                    ])) {
                        $permissionsByRole['member'][] = $permissionName;
                    }
                }
            }
        }

        // Create roles if they don't exist
        $roles = [
            'super-admin' => 'Super Administrator with access to everything',
            'admin' => 'Organization Administrator',
            'manager' => 'Project Manager',
            'member' => 'Team Member',
            'guest' => 'Guest User with limited access',
        ];

        // Role levels (higher number = higher privilege)
        $roleLevels = [
            'super-admin' => 100,
            'admin' => 80,
            'manager' => 60,
            'member' => 40,
            'guest' => 20,
        ];

        foreach ($roles as $roleName => $roleDescription) {
            $role = Role::firstOrCreate(['name' => $roleName], [
                'name' => $roleName,
                'guard_name' => 'web',
                'level' => $roleLevels[$roleName] ?? 0
            ]);

            // For super-admin, we don't assign specific permissions as they get all permissions via Gate::before
            if ($roleName !== 'super-admin') {
                // Sync permissions to the role
                $role->syncPermissions($permissionsByRole[$roleName]);
            }

            $this->command->info("Created role {$roleName} with " . count($permissionsByRole[$roleName]) . " permissions");
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
