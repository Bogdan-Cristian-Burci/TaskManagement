<?php

namespace App\Policies;

use App\Models\BoardType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BoardType $boardType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Only admin or users with specific permissions can create board types
        return $user->hasRole('admin') || $user->hasPermissionTo('manage board types');
    }

    public function update(User $user, BoardType $boardType): bool
    {
        // Only admin or users with specific permissions can update board types
        return $user->hasRole('admin') || $user->hasPermissionTo('manage board types');
    }

    public function delete(User $user, BoardType $boardType): bool
    {
        // Only admin or users with specific permissions can delete board types
        // May want to prevent deletion if boards are using this type
        if ($boardType->boards()->count() > 0) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermissionTo('manage board types');
    }

    public function restore(User $user, BoardType $boardType): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('manage board types');
    }

    public function forceDelete(User $user, BoardType $boardType): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('manage board types');
    }
}
