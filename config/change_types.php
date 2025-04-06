<?php

use App\Enums\ChangeTypeEnum;

return [
    /*
    |--------------------------------------------------------------------------
    | Change Types Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the mapping between change types and their descriptions
    | for use throughout the application.
    |
    */

    'types' => [
        ChangeTypeEnum::STATUS->value => [
            'description' => 'Change status of a task',
        ],
        ChangeTypeEnum::PRIORITY->value => [
            'description' => 'Change priority of a task',
        ],
        ChangeTypeEnum::TASK_TYPE->value => [
            'description' => 'Change type of a task',
        ],
        ChangeTypeEnum::RESPONSIBLE->value => [
            'description' => 'Change responsible of a task',
        ],
        ChangeTypeEnum::REPORTER->value => [
            'description' => 'Change reporter of a task',
        ],
        ChangeTypeEnum::PARENT_TASK->value => [
            'description' => 'Change parent task of a task',
        ],
        ChangeTypeEnum::BOARD->value => [
            'description' => 'Change board of a task',
        ],
        ChangeTypeEnum::PROJECT->value => [
            'description' => 'Change project of a task',
        ],
        ChangeTypeEnum::NAME->value => [
            'description' => 'Change name of an item',
        ],
        ChangeTypeEnum::DESCRIPTION->value => [
            'description' => 'Change description of an item',
        ],
        ChangeTypeEnum::DUE_DATE->value => [
            'description' => 'Change due date of a task',
        ],
        ChangeTypeEnum::ATTACHMENT->value => [
            'description' => 'Change related to an attachment',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute Mapping
    |--------------------------------------------------------------------------
    |
    | Maps model attributes to change types. This is used by the setChangeType
    | method to determine which change type to use for a specific attribute.
    |
    */
    'attribute_mapping' => [
        'status_id' => ChangeTypeEnum::STATUS->value,
        'priority_id' => ChangeTypeEnum::PRIORITY->value,
        'task_type_id' => ChangeTypeEnum::TASK_TYPE->value,
        'responsible_id' => ChangeTypeEnum::RESPONSIBLE->value,
        'reporter_id' => ChangeTypeEnum::REPORTER->value,
        'parent_task_id' => ChangeTypeEnum::PARENT_TASK->value,
        'board_id' => ChangeTypeEnum::BOARD->value,
        'project_id' => ChangeTypeEnum::PROJECT->value,
        'name' => ChangeTypeEnum::NAME->value,
        'description' => ChangeTypeEnum::DESCRIPTION->value,
        'due_date' => ChangeTypeEnum::DUE_DATE->value,
    ],
];
