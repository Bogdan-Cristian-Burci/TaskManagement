<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $permissions =[
            //Board
            'view boards',
            'view board',
            'create board',
            'update board',
            'delete board',

            //Roles and Permissions
            'manage roles',
            'manage permissions',

            //Project
            'view projects',
            'view project',
            'create project',
            'update project',
            'delete project',

            // Team permissions
            'view teams',
            'view team',
            'create team',
            'update team',
            'delete team',
            'manage team members',

            // Organisation permissions
            'view-organisations',
            'create-organisation',
            'update-organisation',
            'delete-organisation',
            'restore-organisation',
            'manage-organisation-members',

            // Tag permissions
            'view tags',
            'view tag',
            'create tag',
            'update tag',
            'delete tag',

            // Task permissions
            'view tasks',
            'view task',
            'create task',
            'update task',
            'delete task',
            'assign task',

            // Attachment permissions
            'view attachments',
            'view attachment',
            'create attachment',
            'update attachment',
            'delete attachment',

            //Board Type permissions
            'manage board types',

            //Change Type permissions

            'manage task settings',

            //Priority permissions
            'view priorities',
            'create priorities',
            'update priorities',
            'delete priorities',

        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::all());

        $projectManager = Role::create(['name' => 'project-manager', 'guard_name' => 'web']);
        $projectManager->givePermissionTo([
            // Project permissions
            'view projects', 'view project', 'create project', 'update project',

            // Board permissions
            'view boards', 'view board', 'create board', 'update board', 'delete board',

            // Team permissions
            'view teams', 'view team', 'manage team members',

            // Tag permissions
            'view tags', 'view tag', 'create tag', 'update tag', 'delete tag',

            // Task permissions
            'view tasks', 'view task', 'create task', 'update task', 'delete task', 'assign task',

            //Priority permissions
            'view priorities',
        ]);

        $developer = Role::create(['name' => 'developer', 'guard_name' => 'web']);
        $developer->givePermissionTo([
            'view projects', 'view project',
            'view boards', 'view board',
            'view teams', 'view team',
            'view tags', 'view tag',
            'view tasks', 'view task', 'update task',
        ]);

        $viewer = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo([
            'view projects', 'view project',
            'view boards', 'view board',
            'view teams', 'view team',
            'view tags', 'view tag',
            'view tasks', 'view task',
        ]);
    }
}
