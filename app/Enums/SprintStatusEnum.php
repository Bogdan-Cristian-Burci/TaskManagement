<?php

namespace App\Enums;

enum SprintStatusEnum: string
{
    case PLANNING = 'planning';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';

    /**
     * Get all values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label for the enum value.
     *
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::PLANNING => 'Planning',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * Check if a transition from current status to new status is valid.
     *
     * @param SprintStatusEnum $newStatus
     * @return bool
     */
    public function canTransitionTo(SprintStatusEnum $newStatus): bool
    {
        $validTransitions = [
            self::PLANNING->value => [self::PLANNING->value, self::ACTIVE->value],
            self::ACTIVE->value => [self::ACTIVE->value, self::COMPLETED->value],
            self::COMPLETED->value => [self::COMPLETED->value]
        ];

        return in_array($newStatus->value, $validTransitions[$this->value]);
    }
}