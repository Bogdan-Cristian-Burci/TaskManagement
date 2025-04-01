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
         return $user->hasPermission('board.viewAny');
    }

    public function view(User $user, BoardType $boardType): bool
    {
        return $user->hasPermission('board.view');
    }

    public function create(User $user): bool
    {
        // Only admin or users with specific permissions can create board types
        return $user->hasPermission('board.create');
    }

    public function update(User $user, BoardType $boardType): bool
    {
        // Only admin or users with specific permissions can update board types
        return $user->hasPermission('board.update');
    }

    public function delete(User $user, BoardType $boardType): bool
    {
        // Only admin or users with specific permissions can delete board types
        // May want to prevent deletion if boards are using this type
        if ($boardType->boards()->count() > 0) {
            return false;
        }

        return  $user->hasPermission('board.delete');
    }

    public function restore(User $user, BoardType $boardType): bool
    {
        return $user->hasPermission('board.restore');
    }

    public function forceDelete(User $user, BoardType $boardType): bool
    {
        return $user->hasPermission('board.forceDelete');
    }
}
