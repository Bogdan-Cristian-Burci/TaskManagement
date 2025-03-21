<?php

/**
 * Role Configuration
 *
 * This file defines all role templates used throughout the application.
 * Each role template has:
 * - name: Unique identifier used in code
 * - display_name: User-friendly name shown in UI
 * - description: Details about the role's purpose
 * - level: Numerical hierarchy level (higher = more authority)
 * - permissions: Array of permissions granted to this role
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | The default role assigned to new users when no specific role is specified.
    |
    */
    'default' => 'member',

    /*
    |--------------------------------------------------------------------------
    | System Roles
    |--------------------------------------------------------------------------
    |
    | System-wide roles that exist across all organizations.
    |
    */
    'system' => [
        'super_admin' => [
            'display_name' => 'Super Administrator',
            'description' => 'System-wide administrator with access to all organizations',
            'level' => 1000,
            'permissions' => 'all', // Special value meaning all permissions
            'is_system' => true,
            'can_be_deleted' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Organization Roles
    |--------------------------------------------------------------------------
    |
    | Roles that exist within an organization context.
    |
    */
    'organization' => [
        'admin' => [
            'display_name' => 'Administrator',
            'description' => 'Full administrative access to the organization',
            'level' => 100,
            'permissions' => 'all', // All permissions within organization
            'is_system' => true,
            'can_be_deleted' => false,
        ],
        'project_manager' => [
            'display_name' => 'Project Manager',
            'description' => 'Manage projects and their resources',
            'level' => 80,
            'permissions' => [
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
            ],
            'is_system' => true,
            'can_be_deleted' => false,
        ],
        'team_leader' => [
            'display_name' => 'Team Leader',
            'description' => 'Lead a team and manage team resources',
            'level' => 60,
            'permissions' => [
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
            ],
            'is_system' => true,
            'can_be_deleted' => false,
        ],
        'member' => [
            'display_name' => 'Member',
            'description' => 'Regular organization member',
            'level' => 40,
            'permissions' => [
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
            ],
            'is_system' => true,
            'can_be_deleted' => false,
        ],
        'guest' => [
            'display_name' => 'Guest',
            'description' => 'Limited view-only access',
            'level' => 10,
            'permissions' => [
                'user.viewAny', 'user.view',
                'project.viewAny', 'project.view',
                'task.viewAny', 'task.view',
                'team.viewAny', 'team.view',
                'board.viewAny', 'board.view',
                'comment.viewAny', 'comment.view',
                'attachment.viewAny', 'attachment.view',
            ],
            'is_system' => true,
            'can_be_deleted' => true,
        ],
    ],
];
