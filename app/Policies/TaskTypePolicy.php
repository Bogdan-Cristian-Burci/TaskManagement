<?php

namespace App\Policies;

use App\Models\TaskType;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any task types.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view task types
    }

    /**
     * Determine whether the user can view the task type.
     *
     * @param User $user
     * @param TaskType $taskType
     * @return bool
     */
    public function view(User $user, TaskType $taskType): bool
    {
        return $taskType->is_system || $taskType->organisation_id === $user->organisation_id;
    }

    /**
     * Determine whether the user can create task types.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return  $user->hasPermission('taskType.create');
    }

    /**
     * Determine whether the user can update the task type.
     *
     * @param User $user
     * @param TaskType $taskType
     * @return bool
     */
    public function update(User $user, TaskType $taskType): bool
    {
        if ($taskType->is_system) {
            return false; // System task types cannot be updated
        }
        return  $user->hasPermission('taskType.update', $taskType->organisation_id );
    }

    /**
     * Determine whether the user can delete the task type.
     *
     * @param User $user
     * @param TaskType $taskType
     * @return bool
     */
    public function delete(User $user, TaskType $taskType): bool
    {
        return $user->hasPermission('taskType.delete');
    }

    /**
     * Determine whether the user can restore the task type.
     *
     * @param User $user
     * @param TaskType $taskType
     * @return bool
     */
    public function restore(User $user, TaskType $taskType): bool
    {
        return $user->hasPermission('taskType.restore');
    }

    /**
     * Determine whether the user can permanently delete the task type.
     *
     * @param User $user
     * @param TaskType $taskType
     * @return bool
     */
    public function forceDelete(User $user, TaskType $taskType): bool
    {
        return $user->hasPermission('taskType.forceDelete');
    }

    /**
     * Determine whether the user can manage task types (for operations like clearing cache).
     *
     * @param User $user
     * @return bool
     */
    public function manage(User $user): bool
    {
        return  $user->hasPermission('manage-settings');
    }
}
