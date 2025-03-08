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
        // Users can view their own profile or users they share an organisation with
        if ($user->id === $model->id) {
            return true;
        }

        // Check if they share any organisations
        return $user->organisations()
            ->whereHas('users', function($query) use ($model) {
                $query->where('users.id', $model->id);
            })
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Only admins and users with specific permissions can create new users
        return $user->hasRole(['admin', 'super-admin']) ||
            $user->hasPermissionTo('user.create');
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
        return $user->hasRole('admin') || $user->id === $model->id;
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
        return $user->hasRole('admin');
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
        // Only admins can restore users
        return $user->hasRole('admin');
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
        // Only super-admins can permanently delete users
        return $user->hasRole('super-admin');
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
        return $user->hasRole('admin');
    }
}
