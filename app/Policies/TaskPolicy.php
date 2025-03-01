<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can see tasks
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        // Users can view tasks if they're responsible, reporter, or part of the project
        return $user->id === $task->responsible_id
            || $user->id === $task->reporter_id
            || $user->projects()->where('projects.id', $task->project_id)->exists();
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create tasks
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        // Users can update tasks if they're responsible, reporter, or admin
        return $user->id === $task->responsible_id
            || $user->id === $task->reporter_id
            || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        // Only project managers or admins can delete tasks
        return $user->hasRole('admin')
            || ($user->projects()->where('projects.id', $task->project_id)->exists()
                && $user->hasPermissionTo('delete task'));
    }

    /**
     * Determine whether the user can restore the task.
     */
    public function restore(User $user, Task $task): bool
    {
        // Same rules as delete
        return $this->delete($user, $task);
    }

    /**
     * Determine whether the user can permanently delete the task.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $user->hasRole('admin');
    }
}
