<?php

namespace App\Policies;

use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StatusPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any statuses.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view statuses
    }

    /**
     * Determine whether the user can view the status.
     *
     * @param User $user
     * @param Status $status
     * @return bool
     */
    public function view(User $user, Status $status): bool
    {
        return true; // All authenticated users can view statuses
    }

    /**
     * Determine whether the user can create statuses.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage workflow settings');
    }

    /**
     * Determine whether the user can update the status.
     *
     * @param User $user
     * @param Status $status
     * @return bool
     */
    public function update(User $user, Status $status): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage workflow settings');
    }

    /**
     * Determine whether the user can delete the status.
     *
     * @param User $user
     * @param Status $status
     * @return bool
     */
    public function delete(User $user, Status $status): bool
    {
        // Only allow deletion if the user has proper permissions and the status is not default
        return ($user->hasRole(['admin', 'project_manager']) ||
                $user->hasPermissionTo('manage workflow settings')) &&
            !$status->is_default;
    }

    /**
     * Determine whether the user can restore the status.
     *
     * @param User $user
     * @param Status $status
     * @return bool
     */
    public function restore(User $user, Status $status): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage workflow settings');
    }

    /**
     * Determine whether the user can permanently delete the status.
     *
     * @param User $user
     * @param Status $status
     * @return bool
     */
    public function forceDelete(User $user, Status $status): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can manage statuses (for operations like clearing cache or reordering).
     *
     * @param User $user
     * @return bool
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(['admin', 'project_manager']) ||
            $user->hasPermissionTo('manage workflow settings');
    }
}
