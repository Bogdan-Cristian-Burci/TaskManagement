<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any boards.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can see boards
    }

    /**
     * Determine whether the user can view the board.
     */
    public function view(User $user, Board $board): bool
    {
        // Users can view boards if they are part of the project
        return $user->projects()->where('projects.id', $board->project_id)->exists();
    }

    /**
     * Determine whether the user can create boards.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create board');
    }

    /**
     * Determine whether the user can update the board.
     */
    public function update(User $user, Board $board): bool
    {
        return $user->hasRole('admin') ||
            ($user->projects()->where('projects.id', $board->project_id)->exists() &&
                $user->hasPermissionTo('update board'));
    }

    /**
     * Determine whether the user can delete the board.
     */
    public function delete(User $user, Board $board): bool
    {
        return $user->hasRole('admin') ||
            ($user->projects()->where('projects.id', $board->project_id)->exists() &&
                $user->hasPermissionTo('delete board'));
    }

    /**
     * Determine whether the user can restore the board.
     */
    public function restore(User $user, Board $board): bool
    {
        return $this->delete($user, $board);
    }

    /**
     * Determine whether the user can permanently delete the board.
     */
    public function forceDelete(User $user, Board $board): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can archive the board.
     */
    public function archive(User $user, Board $board): bool
    {
        return $this->update($user, $board);
    }

    /**
     * Determine whether the user can unarchive the board.
     */
    public function unarchive(User $user, Board $board): bool
    {
        return $this->update($user, $board);
    }

    /**
     * Determine whether the user can duplicate the board.
     */
    public function duplicate(User $user, Board $board): bool
    {
        return $user->hasRole('admin') ||
            ($user->projects()->where('projects.id', $board->project_id)->exists() &&
                $user->hasPermissionTo('create board'));
    }
}
