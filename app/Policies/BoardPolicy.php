<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view boards');
    }

    public function view(User $user, Board $board): bool
    {
        return $user->can('view board');
    }

    public function create(User $user): bool
    {
        return $user->can('create board');
    }

    public function update(User $user, Board $board): bool
    {
        return $user->can('update board');
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->can('delete board');
    }

    public function restore(User $user, Board $board): bool
    {
        return $user->can('delete board');
    }

    public function forceDelete(User $user, Board $board): bool
    {
        return $user->can('delete board');
    }
}
