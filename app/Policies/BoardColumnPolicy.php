<?php

namespace App\Policies;

use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardColumnPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BoardColumn $boardColumn): bool
    {
        // User can view column if they can view the board
        return $user->can('view', $boardColumn->board);
    }

    public function create(User $user): bool
    {
        return $user->can('create', \App\Models\Board::class);
    }

    public function update(User $user, BoardColumn $boardColumn): bool
    {
        // User can update column if they can update the board
        return $user->can('update', $boardColumn->board);
    }

    public function delete(User $user, BoardColumn $boardColumn): bool
    {
        // User can delete column if they can update the board
        return $user->can('update', $boardColumn->board);
    }

    public function restore(User $user, BoardColumn $boardColumn): bool
    {
        // User can delete column if they can update the board
        return $user->can('update', $boardColumn->board);
    }

    public function forceDelete(User $user, BoardColumn $boardColumn): bool
    {
        // User can delete column if they can update the board
        return $user->can('update', $boardColumn->board);
    }
}
