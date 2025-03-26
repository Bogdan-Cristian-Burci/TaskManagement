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
        return $user->hasPermission('board.view', $boardColumn->board->getOrganisationIdAttribute());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('board.create');
    }

    public function update(User $user, BoardColumn $boardColumn): bool
    {
        // User can update column if they can update the board
        return $user->hasPermission('board.update',$boardColumn->board->getOrganisationIdAttribute());
    }

    public function delete(User $user, BoardColumn $boardColumn): bool
    {
        // User can delete column if they can update the board
        return $user->hasPermission('board.delete',$boardColumn->board->getOrganisationIdAttribute());
    }

    public function restore(User $user, BoardColumn $boardColumn): bool
    {
        // User can delete column if they can update the board
        return $user->hasPermission('board.restore',$boardColumn->board->getOrganisationIdAttribute());
    }

    public function forceDelete(User $user, BoardColumn $boardColumn): bool
    {
        // User can delete column if they can update the board
        return $user->hasPermission('board.forceDelete',$boardColumn->board->getOrganisationIdAttribute());
    }
}
