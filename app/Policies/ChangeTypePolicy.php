<?php

namespace App\Policies;

use App\Models\ChangeType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChangeTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any change types.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view change types
    }

    /**
     * Determine whether the user can view the change type.
     *
     * @param User $user
     * @param ChangeType $changeType
     * @return bool
     */
    public function view(User $user, ChangeType $changeType): bool
    {
        return true; // All authenticated users can view change types
    }

    /**
     * Determine whether the user can create change types.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return  $user->hasPermission('manage-changeTypes');
    }

    /**
     * Determine whether the user can update the change type.
     *
     * @param User $user
     * @param ChangeType $changeType
     * @return bool
     */
    public function update(User $user, ChangeType $changeType): bool
    {
        return  $user->hasPermission('manage-changeTypes');
    }

    /**
     * Determine whether the user can delete the change type.
     *
     * @param User $user
     * @param ChangeType $changeType
     * @return bool
     */
    public function delete(User $user, ChangeType $changeType): bool
    {
        return  $user->hasPermission('manage-changeTypes');
    }

    /**
     * Determine whether the user can restore the change type.
     *
     * @param User $user
     * @param ChangeType $changeType
     * @return bool
     */
    public function restore(User $user, ChangeType $changeType): bool
    {
        return  $user->hasPermission('manage-changeTypes');
    }

    /**
     * Determine whether the user can permanently delete the change type.
     *
     * @param User $user
     * @param ChangeType $changeType
     * @return bool
     */
    public function forceDelete(User $user, ChangeType $changeType): bool
    {
        return $user->hasPermission('manage-changeTypes');
    }

    /**
     * Determine whether the user can manage change types (for operations like clearing cache).
     *
     * @param User $user
     * @return bool
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('manage-changeTypes');
    }
}
