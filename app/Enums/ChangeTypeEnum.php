<?php

namespace App\Enums;

enum ChangeTypeEnum: string
{
    case STATUS = 'status';
    case PRIORITY = 'priority';
    case TASK_TYPE = 'task_type';
    case RESPONSIBLE = 'responsible';
    case REPORTER = 'reporter';
    case PARENT_TASK = 'parent_task';
    case BOARD = 'board';
    case PROJECT = 'project';
    case NAME = 'name';
    case DESCRIPTION = 'description';
    case DUE_DATE = 'due_date';
    case ATTACHMENT = 'attachment';

    /**
     * Get all enum values as an array of strings.
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the change type value for a specific model attribute
     *
     * @param string $attribute
     * @return string|null
     */
    public static function fromAttribute(string $attribute): ?string
    {
        return match($attribute) {
            'status_id' => self::STATUS->value,
            'priority_id' => self::PRIORITY->value,
            'task_type_id' => self::TASK_TYPE->value,
            'responsible_id' => self::RESPONSIBLE->value,
            'reporter_id' => self::REPORTER->value,
            'parent_task_id' => self::PARENT_TASK->value,
            'board_id' => self::BOARD->value,
            'project_id' => self::PROJECT->value,
            'name' => self::NAME->value,
            'description' => self::DESCRIPTION->value,
            'due_date' => self::DUE_DATE->value,
            default => null,
        };
    }
}
