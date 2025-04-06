<?php

/**
 * Permission Configuration
 *
 * This file defines all permissions used throughout the application.
 * All permissions should follow standard naming conventions:
 * - Model-based: "{model}.{action}" (e.g., "user.create")
 * - Special: "manage-{resource}" (e.g., "manage-roles")
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Resource Models
    |--------------------------------------------------------------------------
    |
    | List of all models that need standard permission sets.
    | These will be used to generate the standardized permissions.
    |
    */
    'models' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Standard Actions
    |--------------------------------------------------------------------------
    |
    | Standard CRUD actions that apply to all models.
    | These combine with models to create permissions like "user.view".
    |
    */
    'standard_actions' => [
        'viewAny',   // index method
        'view',      // show method
        'create',    // create/store methods
        'update',    // edit/update methods
        'delete',    // destroy method
        'forceDelete', // forceDelete method
        'restore',   // restore method
    ],

    /*
    |--------------------------------------------------------------------------
    | Extended Actions
    |--------------------------------------------------------------------------
    |
    | Model-specific actions beyond standard CRUD operations.
    | These are specialized actions for particular models.
    |
    */
    'extended_actions' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Custom permissions that don't follow the model.action pattern.
    |
    */
    'custom' => [
        'manage-roles',
        'manage-permissions',
        'manage-users',
        'manage-organisations',
        'manage-teams',
        'manage-projects',
        'manage-settings',
        'manage-statuses',
        'manage-changeTypes',
        'manage-priorities'
    ],
];
