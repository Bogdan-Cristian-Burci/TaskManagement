<?php

namespace App\Policies;

use App\Models\Priority;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PriorityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any priorities.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view priorities
    }

    /**
     * Determine whether the user can view the priority.
     *
     * @param User $user
     * @param Priority $priority
     * @return bool
     */
    public function view(User $user, Priority $priority): bool
    {
        return true; // All authenticated users can view priorities
    }

    /**
     * Determine whether the user can create priorities.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage task settings');
    }

    /**
     * Determine whether the user can update the priority.
     *
     * @param User $user
     * @param Priority $priority
     * @return bool
     */
    public function update(User $user, Priority $priority): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage task settings');
    }

    /**
     * Determine whether the user can delete the priority.
     *
     * @param User $user
     * @param Priority $priority
     * @return bool
     */
    public function delete(User $user, Priority $priority): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage task settings');
    }

    /**
     * Determine whether the user can restore the priority.
     *
     * @param User $user
     * @param Priority $priority
     * @return bool
     */
    public function restore(User $user, Priority $priority): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage task settings');
    }

    /**
     * Determine whether the user can permanently delete the priority.
     *
     * @param User $user
     * @param Priority $priority
     * @return bool
     */
    public function forceDelete(User $user, Priority $priority): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can manage priorities (for operations like clearing cache or reordering).
     *
     * @param User $user
     * @return bool
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage task settings');
    }
}
