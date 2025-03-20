<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTemplate;
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
            'role',
            'permission',
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
            'manage-roles',
            'manage-permissions',
            'manage-users',
            'manage-organisations',
            'manage-teams',
            'manage-projects',
            'manage-settings',
        ];

        // 1. Create all the permissions
        $allPermissions = [];
        $permissionIds = [];

        // Create standard permissions for all models
        foreach ($models as $model) {
            foreach ($standardActions as $action) {
                $permissionName = "{$model}.{$action}";
                $displayName = ucfirst($action) . ' ' . ucfirst($model);

                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    [
                        'name' => $permissionName,
                        'display_name' => $displayName,
                        'description' => "Allows user to {$action} {$model}s"
                    ]
                );

                $allPermissions[] = $permissionName;
                $permissionIds[] = $permission->id;
            }

            // Add model-specific extended actions
            if (isset($extendedActions[$model])) {
                foreach ($extendedActions[$model] as $extendedAction) {
                    $permissionName = "{$model}.{$extendedAction}";
                    $displayName = ucfirst($extendedAction) . ' ' . ucfirst($model);

                    $permission = Permission::firstOrCreate(
                        ['name' => $permissionName],
                        [
                            'name' => $permissionName,
                            'display_name' => $displayName,
                            'description' => "Allows user to {$extendedAction} {$model}s"
                        ]
                    );

                    $allPermissions[] = $permissionName;
                    $permissionIds[] = $permission->id;
                }
            }
        }

        // Create custom permissions
        foreach ($customPermissions as $permissionName) {
            $displayName = ucwords(str_replace('-', ' ', $permissionName));

            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                [
                    'name' => $permissionName,
                    'display_name' => $displayName,
                    'description' => "Allows user to {$displayName}"
                ]
            );

            $allPermissions[] = $permissionName;
            $permissionIds[] = $permission->id;
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

        // 6. Create the standard templates
        $adminTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'admin'
            ],
            [
                'name' => 'admin',
                'display_name' => 'Admin Template',
                'description' => 'Full administrative access',
                'level' => 100,
                'is_system' => true
            ]
        );

        $teamLeaderTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'team_leader'
            ],
            [
                'name' => 'team_leader',
                'display_name' => 'Team Leader Template',
                'description' => 'Team leadership responsibilities',
                'level' => 60,
                'is_system' => true
            ]
        );

        $projectManagerTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'project_manager'
            ],
            [
                'name' => 'project_manager',
                'display_name' => 'Project Manager Template',
                'description' => 'Project management responsibilities',
                'level' => 80,
                'is_system' => true
            ]
        );

        $memberTemplate = RoleTemplate::firstOrCreate(
            [
                'name' => 'member'
            ],
            [
                'name' => 'member',
                'display_name' => 'Member Template',
                'description' => 'Standard team member access',
                'level' => 40,
                'is_system' => true
            ]
        );

        // Assign permissions to templates
        $this->assignPermissionsToTemplate($adminTemplate, $adminPermissions);
        $this->assignPermissionsToTemplate($teamLeaderTemplate, $teamLeaderPermissions);
        $this->assignPermissionsToTemplate($projectManagerTemplate, $projectManagerPermissions);
        $this->assignPermissionsToTemplate($memberTemplate, $memberPermissions);

        // 7. Create roles for the demo org based on templates
        $this->createOrganizationRolesForOrg($demoOrg->id, $demoUser->id);

        $this->command->info('Permissions, templates and roles seeded successfully!');
    }

    /**
     * Assign permissions to a role template by name
     *
     * @param RoleTemplate $template
     * @param array $permissionNames
     */
    private function assignPermissionsToTemplate(RoleTemplate $template, array $permissionNames): void
    {
        // Get permission IDs for the provided permission names
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id')->toArray();

        // Sync permissions to template
        $template->permissions()->sync($permissionIds);
    }

    /**
     * Create organization-specific roles for all organizations
     */
    public function createOrganizationRoles(): void
    {
        // Get all organizations
        $organizations = Organisation::all();
        $this->command->info("Creating organization-specific roles for " . $organizations->count() . " organizations");

        foreach ($organizations as $organization) {
            $this->createOrganizationRolesForOrg(
                $organization->id,
                $organization->owner_id
            );
        }
    }

    /**
     * Create roles from templates for a specific organization
     *
     * @param int $organisationId
     * @param int $ownerId
     * @return void
     */
    public function createOrganizationRolesForOrg(
        int $organisationId,
        ?int $ownerId = null
    ): void
    {
        if (!$organisationId) {
            return;
        }

        // Get all templates
        $templates = RoleTemplate::where('is_system', true)->get();

        foreach ($templates as $template) {
            // Create role from template
            $role = Role::createFromTemplate($template, $organisationId);
            $roleName = $template->name;

            $this->command->info("Created/updated role {$roleName} for organisation {$organisationId} using template {$template->id}");

            // If this is the admin role, assign it to the owner
            if ($roleName === 'admin' && $ownerId) {
                $this->assignAdminRoleToOwner($ownerId, $organisationId, $role->id);
            }
        }
    }

    /**
     * Assign admin role to organization owner
     */
    protected function assignAdminRoleToOwner(int $ownerId, int $organisationId, int $roleId): void
    {
        $owner = User::find($ownerId);
        if (!$owner) {
            return;
        }

        // Detach any existing roles for this user in this org
        $owner->roles()
            ->whereHas('organisation', function ($q) use ($organisationId) {
                $q->where('organisations.id', $organisationId);
            })
            ->detach();

        // Attach admin role
        $owner->roles()->attach($roleId);

        $this->command->info("User ID {$ownerId} has been assigned the admin role for organisation {$organisationId}");
    }

    /**
     * Ensure an admin user has the required permissions
     */
    public static function addMissingPermissionsToAdmin($userId, $organisationId): bool
    {
        // Find the user
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // Find the admin role for this organization
        $adminRole = Role::where('name', 'admin')
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$adminRole) {
            return false;
        }

        // Ensure the user has the admin role
        $hasRole = $user->roles()
            ->where('roles.id', $adminRole->id)
            ->exists();

        if (!$hasRole) {
            // Assign admin role to the user
            $user->attachRole($adminRole, $organisationId);
        }

        // Critical permissions - make sure they exist
        $criticalPermissions = [
            'role.view',
            'role.viewAny',
            'permission.view',
            'permission.viewAny',
            'manage-roles',
        ];

        foreach ($criticalPermissions as $permissionName) {
            // Make sure permission exists
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                [
                    'name' => $permissionName,
                    'display_name' => ucwords(str_replace(['.', '-'], ' ', $permissionName)),
                    'description' => 'Critical admin permission'
                ]
            );

            // Make sure role has permission
            if (!$adminRole->hasPermission($permission)) {
                $adminRole->permissions()->attach($permission->id);
            }
        }

        return true;
    }
}
