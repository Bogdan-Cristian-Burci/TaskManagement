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
        // Debug logging to see what's happening
    \Log::debug('UserPolicy::update check', [
        'checking_user_id' => $user->id,
        'target_user_id' => $model->id,
        'is_same_user' => $user->id === $model->id,
        'checking_user_roles' => $user->roles->pluck('name'),
        'checking_user_has_admin' => $user->hasRole('admin'),
        'checking_user_permissions' => $user->getAllPermissions()->pluck('name'),
        'direct_db_roles' => \DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', get_class($user))
            ->pluck('roles.name')
            ->toArray(),
    ]);
        // Original condition
        $hasAdminRole = $user->hasRole('admin');
        $isSameUser = $user->id === $model->id;

        \Log::debug('UserPolicy::update result', [
            'has_admin_role' => $hasAdminRole,
            'is_same_user' => $isSameUser,
            'final_result' => $hasAdminRole || $isSameUser
        ]);

        return $hasAdminRole || $isSameUser;
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
