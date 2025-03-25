<?php
// config/board_templates.php

return [
    /*
    |--------------------------------------------------------------------------
    | System Board Templates
    |--------------------------------------------------------------------------
    |
    | These templates are used as defaults when creating boards in the system.
    | They can be customized by organizations but will never be removed.
    | The key is used as a unique identifier for the template.
    |
    */

    'kanban' => [
        'name' => 'Kanban Board',
        'description' => 'A Kanban board with To Do, In Progress, and Done columns',
        'columns_structure' => [
            [
                'name' => 'To Do',
                'color' => '#3498DB', // Blue
                'wip_limit' => null,
                'status_id' => 1, // Maps to "To Do" status
                'allowed_transitions' => null, // No restrictions
            ],
            [
                'name' => 'In Progress',
                'color' => '#F39C12', // Orange
                'wip_limit' => 3, // Example WIP limit
                'status_id' => 2, // Maps to "In Progress" status
                'allowed_transitions' => [1, 3], // Can only transition to/from To Do and Done
            ],
            [
                'name' => 'Done',
                'color' => '#27AE60', // Green
                'wip_limit' => null,
                'status_id' => 3, // Maps to "Done" status
                'allowed_transitions' => null, // No restrictions
            ],
        ],
        'settings' => [
            'allow_wip_limits' => true,
            'track_cycle_time' => true,
            'default_view' => 'kanban',
            'allow_subtasks' => true,
            'show_assignee_avatars' => true,
            'enable_task_estimation' => false,
        ],
    ],

    'scrum' => [
        'name' => 'Scrum Board',
        'description' => 'A Scrum board with Backlog, Sprint Backlog, In Progress, Testing, and Done columns',
        'columns_structure' => [
            [
                'name' => 'Backlog',
                'color' => '#95A5A6', // Gray
                'wip_limit' => null,
                'status_id' => 1, // Maps to "To Do" status
                'allowed_transitions' => [2], // Can only move to Sprint Backlog
            ],
            [
                'name' => 'Sprint Backlog',
                'color' => '#3498DB', // Blue
                'wip_limit' => null,
                'status_id' => 1, // Maps to "To Do" status
                'allowed_transitions' => [1, 3], // Can move to Backlog or In Progress
            ],
            [
                'name' => 'In Progress',
                'color' => '#F39C12', // Orange
                'wip_limit' => null,
                'status_id' => 2, // Maps to "In Progress" status
                'allowed_transitions' => [2, 4], // Can move to Sprint Backlog or Testing
            ],
            [
                'name' => 'Testing',
                'color' => '#9B59B6', // Purple
                'wip_limit' => null,
                'status_id' => 2, // Maps to "In Progress" status
                'allowed_transitions' => [3, 5], // Can move to In Progress or Done
            ],
            [
                'name' => 'Done',
                'color' => '#27AE60', // Green
                'wip_limit' => null,
                'status_id' => 3, // Maps to "Done" status
                'allowed_transitions' => [4], // Can only move from Testing
            ],
        ],
        'settings' => [
            'allow_wip_limits' => true,
            'sprint_support' => true,
            'track_cycle_time' => true,
            'default_view' => 'scrum',
            'story_points' => true,
            'burndown_chart' => true,
            'velocity_tracking' => true,
            'allow_subtasks' => true,
            'show_assignee_avatars' => true,
            'enable_task_estimation' => true,
        ],
    ],

    'basic' => [
        'name' => 'Basic Board',
        'description' => 'A simple task board with To Do and Done columns',
        'columns_structure' => [
            [
                'name' => 'To Do',
                'color' => '#3498DB', // Blue
                'wip_limit' => null,
                'status_id' => 1, // Maps to "To Do" status
                'allowed_transitions' => null, // No restrictions
            ],
            [
                'name' => 'Done',
                'color' => '#27AE60', // Green
                'wip_limit' => null,
                'status_id' => 3, // Maps to "Done" status
                'allowed_transitions' => null, // No restrictions
            ],
        ],
        'settings' => [
            'allow_wip_limits' => false,
            'track_cycle_time' => false,
            'default_view' => 'list',
            'allow_subtasks' => true,
            'show_assignee_avatars' => true,
            'enable_task_estimation' => false,
        ],
    ],
];
