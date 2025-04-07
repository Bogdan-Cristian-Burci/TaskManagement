<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Task Workflow Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the task statuses and their allowed transitions.
    | This is the single source of truth for all workflow-related definitions.
    |
    */

    'statuses' => [
        [
            'name' => 'To Do',
            'description' => 'Tasks that are ready to be worked on',
            'color' => '#3498db', // Blue
            'icon' => 'clipboard-list',
            'is_default' => true,
            'position' => 1,
            'category' => 'todo'
        ],
        [
            'name' => 'In Progress',
            'description' => 'Tasks currently being worked on',
            'color' => '#f39c12', // Orange
            'icon' => 'spinner',
            'is_default' => false,
            'position' => 2,
            'category' => 'in_progress'
        ],
        [
            'name' => 'In Review',
            'description' => 'Tasks that are being reviewed',
            'color' => '#9b59b6', // Purple
            'icon' => 'search',
            'is_default' => false,
            'position' => 3,
            'category' => 'in_progress'
        ],
        [
            'name' => 'Done',
            'description' => 'Tasks that have been completed',
            'color' => '#2ecc71', // Green
            'icon' => 'check-circle',
            'is_default' => false,
            'position' => 4,
            'category' => 'done'
        ],
        [
            'name' => 'Blocked',
            'description' => 'Tasks that are blocked by external factors',
            'color' => '#e74c3c', // Red
            'icon' => 'ban',
            'is_default' => false,
            'position' => 5,
            'category' => 'todo'
        ],
        [
            'name' => 'Canceled',
            'description' => 'Tasks that are no longer necessary',
            'color' => '#95a5a6', // Gray
            'icon' => 'times-circle',
            'is_default' => false,
            'position' => 6,
            'category' => 'canceled'
        ],
    ],

    'global_transitions' => [
        ['from' => 'To Do', 'to' => 'In Progress', 'name' => 'Start Work'],
        ['from' => 'To Do', 'to' => 'Blocked', 'name' => 'Block Task'],
        ['from' => 'To Do', 'to' => 'Canceled', 'name' => 'Cancel Task'],

        ['from' => 'In Progress', 'to' => 'In Review', 'name' => 'Submit for Review'],
        ['from' => 'In Progress', 'to' => 'Done', 'name' => 'Complete Without Review'],
        ['from' => 'In Progress', 'to' => 'Blocked', 'name' => 'Block Work'],
        ['from' => 'In Progress', 'to' => 'Canceled', 'name' => 'Cancel Work'],
        ['from' => 'In Progress', 'to' => 'To Do', 'name' => 'Move Back to To Do'],

        ['from' => 'In Review', 'to' => 'In Progress', 'name' => 'Request Changes'],
        ['from' => 'In Review', 'to' => 'Done', 'name' => 'Approve Work'],
        ['from' => 'In Review', 'to' => 'Blocked', 'name' => 'Block in Review'],

        ['from' => 'Done', 'to' => 'In Progress', 'name' => 'Reopen'],

        ['from' => 'Blocked', 'to' => 'In Progress', 'name' => 'Unblock'],
        ['from' => 'Blocked', 'to' => 'Canceled', 'name' => 'Cancel Blocked Task'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Board Templates
    |--------------------------------------------------------------------------
    |
    | These define the visual structure of board types with columns mapped to statuses.
    | Each board can have custom transitions that override or extend global ones.
    |
    */
    'board_templates' => [
        'kanban' => [
            'name' => 'Kanban Board',
            'description' => 'A Kanban board with To Do, In Progress, and Done columns',
            'columns' => [
                [
                    'name' => 'To Do',
                    'color' => '#3B82F6', // Cool Blue
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'To Do',
                        'category'=>'to_do'
                    ],
                ],
                [
                    'name' => 'In Progress',
                    'color' => '#F39C12', // Warm Yellow
                    'wip_limit' => 3,
                    'status' => [
                        'name'=>'In Progress',
                        'category'=>'in_progress'
                    ],
                ],
                [
                    'name' => 'In Review',
                    'color' => '#8B5CF6', // Violet
                    'wip_limit' => 3,
                    'status' => [
                        'name'=>'In Review',
                        'category'=>'in_progress'
                    ],
                ],
                [
                    'name' => 'Blocked',
                    'color' => '#EF4444', // Alert Red
                    'wip_limit' => 3,
                    'status' => [
                        'name'=>'Blocked',
                        'category'=>'to_do'
                    ],
                ],
                [
                    'name' => 'Done',
                    'color' => '#10B981', // Success Green
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'Done',
                        'category'=>'done'
                    ],
                ],
                [
                    'name' => 'Canceled',
                    'color' => '#9CA3AF', // Neutral Gray
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'Canceled',
                        'category'=>'canceled'
                    ],
                ],
            ],
            'board_specific_transitions' => [
                // Define any board-specific transitions that override or extend the global ones
                // ['from' => 'To Do', 'to' => 'Done', 'name' => 'Fast-track Completion']
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
            'columns' => [
                [
                    'name' => 'Backlog',
                    'color' => '#95A5A6', // Gray
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'Backlog',
                        'category'=>'to_do'
                    ],
                ],
                [
                    'name' => 'Sprint Backlog',
                    'color' => '#3498DB', // Blue
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'Sprint Backlog',
                        'category'=>'to_do'
                    ],
                ],
                [
                    'name' => 'In Progress',
                    'color' => '#F39C12', // Orange
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'In Progress',
                        'category'=>'in_progress',
                    ],
                ],
                [
                    'name' => 'Testing',
                    'color' => '#9B59B6', // Purple
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'Testing',
                        'category'=>'in_progress'
                    ],
                ],
                [
                    'name' => 'Done',
                    'color' => '#27AE60', // Green
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'Done',
                        'category'=>'done'
                    ],
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
            'columns' => [
                [
                    'name' => 'To Do',
                    'color' => '#3498DB', // Blue
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'To Do',
                        'category'=>'to_do'
                    ],
                ],
                [
                    'name' => 'In Progress',
                    'color' => '#F39C12', // Orange
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'In Progress',
                        'category'=>'in_progress'
                    ],
                ],
                [
                    'name' => 'Done',
                    'color' => '#27AE60', // Green
                    'wip_limit' => null,
                    'status' => [
                        'name'=>'Done',
                        'category'=>'done'
                    ],
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
    ],
];
