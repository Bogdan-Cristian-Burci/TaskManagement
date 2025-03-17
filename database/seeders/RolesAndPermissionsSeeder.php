<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Organisation;
use App\Models\RoleTemplate;

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

        // 1. Create all the permissions
        $allPermissions = [];

        // Create standard permissions for all models
        foreach ($models as $model) {
            foreach ($standardActions as $action) {
                $permissionName = "{$model}.{$action}";
                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'api'
                ]);

                $allPermissions[] = $permissionName;
            }

            // Add model-specific extended actions
            if (isset($extendedActions[$model])) {
                foreach ($extendedActions[$model] as $extendedAction) {
                    $permissionName = "{$model}.{$extendedAction}";
                    Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'api'
                    ]);

                    $allPermissions[] = $permissionName;
                }
            }
        }

        // Create custom permissions
        foreach ($customPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api'
            ]);

            $allPermissions[] = $permissionName;
        }

        // 2. Create or get the Demo Organization
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Admin',
                'email' => 'demo@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now()
            ]
        );

        // 3. Create or get the Demo Organization with all required fields
        $demoOrg = Organisation::firstOrCreate(
            ['name' => 'Demo Organization'],
            [
                'name' => 'Demo Organization',
                'unique_id' => 'demo-' . Str::random(8),  // Generate a unique ID
                'description' => 'Standard organization for templates and demo purposes',
                'owner_id' => $demoUser->id,
                'created_by' => $demoUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // 4. Update the demo user's organization_id
        if ($demoUser->organisation_id !== $demoOrg->id) {
            $demoUser->update(['organisation_id' => $demoOrg->id]);
        }
        $this->command->info('Demo Organization created with ID: ' . $demoOrg->id);

        // 5. Define template permissions for each role type

        // Admin template - gets ALL permissions
        $adminPermissions = $allPermissions;

        // Team Leader permissions
        $teamLeaderPermissions = [
            // User permissions
            'user.viewAny', 'user.view',

            // Team permissions
            'team.viewAny', 'team.view', 'team.create', 'team.update',
            'team.addMember', 'team.removeMember', 'team.changeLead',

            // Task permissions
            'task.viewAny', 'task.view', 'task.create', 'task.update',
            'task.assign', 'task.changeStatus', 'task.changePriority',

            // Project permissions
            'project.viewAny', 'project.view',

            // Board permissions
            'board.viewAny', 'board.view',

            // Comment & attachment permissions
            'comment.viewAny', 'comment.view', 'comment.create', 'comment.update',
            'attachment.viewAny', 'attachment.view', 'attachment.create', 'attachment.delete',
        ];

        // Project Manager permissions
        $projectManagerPermissions = [
            // User permissions
            'user.viewAny', 'user.view',

            // Project permissions
            'project.viewAny', 'project.view', 'project.create', 'project.update',
            'project.addMember', 'project.removeMember',

            // Task permissions
            'task.viewAny', 'task.view', 'task.create', 'task.update', 'task.delete',
            'task.assign', 'task.changeStatus', 'task.changePriority',
            'task.addLabel', 'task.removeLabel', 'task.moveTask',

            // Team permissions
            'team.viewAny', 'team.view',

            // Board permissions
            'board.viewAny', 'board.view', 'board.create', 'board.update',
            'board.reorderColumns', 'board.addColumn',

            // Status & Priority permissions
            'status.viewAny', 'status.view', 'status.create', 'status.update',
            'priority.viewAny', 'priority.view',

            // Comment & attachment permissions
            'comment.viewAny', 'comment.view', 'comment.create', 'comment.update', 'comment.delete',
            'attachment.viewAny', 'attachment.view', 'attachment.create', 'attachment.update', 'attachment.delete',
        ];

        // Member permissions
        $memberPermissions = [
            // User permissions
            'user.viewAny', 'user.view',

            // Project permissions
            'project.viewAny', 'project.view',

            // Task permissions
            'task.viewAny', 'task.view', 'task.create',
            'task.changeStatus',

            // Team permissions
            'team.viewAny', 'team.view',

            // Board permissions
            'board.viewAny', 'board.view',

            // Comment & attachment permissions
            'comment.viewAny', 'comment.view', 'comment.create',
            'attachment.viewAny', 'attachment.view', 'attachment.create',
        ];

        // 6. Create the standard templates in Demo Organization
        $adminTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'Admin Template',
                'organisation_id' => $demoOrg->id
            ],
            [
                'name' => 'Admin Template',
                'description' => 'Full administrative access',
                'permissions' => $adminPermissions,
                'organisation_id' => $demoOrg->id
            ]
        );

        $teamLeaderTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'Team Leader Template',
                'organisation_id' => $demoOrg->id
            ],
            [
                'name' => 'Team Leader Template',
                'description' => 'Team leadership responsibilities',
                'permissions' => $teamLeaderPermissions,
                'organisation_id' => $demoOrg->id
            ]
        );

        $projectManagerTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'Project Manager Template',
                'organisation_id' => $demoOrg->id
            ],
            [
                'name' => 'Project Manager Template',
                'description' => 'Project management responsibilities',
                'permissions' => $projectManagerPermissions,
                'organisation_id' => $demoOrg->id
            ]
        );

        $memberTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'Member Template',
                'organisation_id' => $demoOrg->id
            ],
            [
                'name' => 'Member Template',
                'description' => 'Standard team member access',
                'permissions' => $memberPermissions,
                'organisation_id' => $demoOrg->id
            ]
        );

        // 7. Create standard roles in Demo Organization with templates
        $standardRoles = [
            'admin' => [
                'level' => 100,
                'description' => 'Organization Administrator',
                'template' => $adminTemplate
            ],
            'team_leader' => [
                'level' => 60,
                'description' => 'Team Leader',
                'template' => $teamLeaderTemplate
            ],
            'project_manager' => [
                'level' => 80,
                'description' => 'Project Manager',
                'template' => $projectManagerTemplate
            ],
            'member' => [
                'level' => 40,
                'description' => 'Team Member',
                'template' => $memberTemplate
            ]
        ];

        foreach ($standardRoles as $roleName => $roleData) {
            Role::firstOrCreate(
                [
                    'name' => $roleName,
                    'organisation_id' => $demoOrg->id
                ],
                [
                    'name' => $roleName,
                    'guard_name' => 'api',
                    'organisation_id' => $demoOrg->id,
                    'level' => $roleData['level'],
                    'template_id' => $roleData['template']->id
                ]
            );
        }

        $this->command->info('Standard roles and templates created for Demo Organization');

        // 8. Create organization-specific roles using templates
        $this->createOrganizationRoles($standardRoles);

        $this->command->info('Permissions, templates and roles seeded successfully!');
    }

    /**
     * Create organization-specific roles for all organizations
     *
     * @param array $standardRoles
     */
    public function createOrganizationRoles(array $standardRoles): void
    {
        // Get the demo organization
        $demoOrg = Organisation::where('name', 'Demo Organization')->first();

        // Get all organizations except demo
        $organizations = Organisation::where('id', '!=', $demoOrg->id)->get();
        $this->command->info("Creating organization-specific roles for " . $organizations->count() . " organizations");

        foreach ($organizations as $organization) {
            $this->createOrganizationRolesForOrg(
                $organization->id,
                $organization->owner_id,
                $standardRoles
            );
        }
    }

    /**
     * Create roles and assign templates for a specific organization
     *
     * @param int $organizationId
     * @param int $ownerId
     * @param array $standardRoles
     * @return void
     */
    public function createOrganizationRolesForOrg(
        int   $organizationId,
        int   $ownerId,
        array $standardRoles
    ): void
    {
        if (!$organizationId) {
            return;
        }

        // Get the demo organization
        $demoOrg = Organisation::where('name', 'Demo Organization')->first();

        // Create roles for this organization using demo templates
        foreach ($standardRoles as $roleName => $roleData) {
            // Check if role already exists for this organization
            $role = Role::where('name', $roleName)
                ->where('organisation_id', $organizationId)
                ->first();

            // Get the template from demo org
            $templateId = $roleData['template']->id;

            if (!$role) {
                // Create new role with template reference
                $role = Role::create([
                    'name' => $roleName,
                    'guard_name' => 'api',
                    'organisation_id' => $organizationId,
                    'description' => $roleData['description'],
                    'level' => $roleData['level'],
                    'template_id' => $templateId
                ]);

                $this->command->info("Created role {$roleName} for organisation {$organizationId} using template {$templateId}");
            } else {
                // Update existing role to use the template
                $role->update([
                    'template_id' => $templateId,
                    'level' => $roleData['level']
                ]);

                $this->command->info("Updated role {$roleName} for organisation {$organizationId} to use template {$templateId}");
            }

            // If this is the admin role, assign it to the owner
            if ($roleName === 'admin' && $ownerId) {
                $this->assignAdminRoleToOwner($ownerId, $organizationId, $role->id);
            }
        }
    }

    /**
     * Assign admin role to organization owner
     */
    protected function assignAdminRoleToOwner(int $ownerId, int $organizationId, int $roleId): void
    {
        $owner = User::find($ownerId);
        if (!$owner) {
            return;
        }

        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Remove existing roles for this user in this organization
        DB::table('model_has_roles')
            ->where('model_id', $owner->id)
            ->where('model_type', get_class($owner))
            ->where('organisation_id', $organizationId)
            ->delete();

        // Assign admin role to owner
        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_id' => $owner->id,
            'model_type' => get_class($owner),
            'organisation_id' => $organizationId,
        ]);

        $this->command->info("User ID {$ownerId} has been assigned the admin role for organisation {$organizationId}");
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

        // If the role uses a template, no need to add individual permissions
        if ($adminRole->template_id) {
            return true;
        }

        // If no template, ensure critical permissions exist (backward compatibility)
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
