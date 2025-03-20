<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view users they share an organisation with
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.view', $user->organisation_id);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.create', $user->organisation_id);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function update(User $user, User $model): bool
    {
        // Allow users to update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermission('users.update', $user->organisation_id);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function delete(User $user, User $model): bool
    {
        // Prevent users from deleting themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasPermission('users.delete', $user->organisation_id);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasPermission('users.restore', $user->organisation_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermission('users.forceDelete', $user->organisation_id);
    }

    /**
     * Determine if the user can manage other users' roles.
     *
     * @param User $user
     * @param User $model
     * @return bool
     */
    public function manageRoles(User $user, User $model): bool
    {
        // Prevent users from changing their own roles
        if ($user->id === $model->id) {
            return false;
        }

        // Check if user has higher role level than the target user
        $highestUserRole = $user->getHighestRole($user->organisation_id);
        $highestTargetRole = $model->getHighestRole($user->organisation_id);

        $hasHigherRole = false;
        if ($highestUserRole && $highestTargetRole) {
            $hasHigherRole = $highestUserRole->getLevel() > $highestTargetRole->getLevel();
        } elseif ($highestUserRole) {
            // If target has no role, user can manage them if they have any role
            $hasHigherRole = true;
        }

        return $user->hasPermission('roles.manage', $user->organisation_id) && $hasHigherRole;
    }
}
