<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user,Task $task): bool
    {
        return $user->can('view', $task);
    }

    public function view(User $user, Comment $comment): bool
    {
        return $user->can('view', $comment->task);
    }

    public function create(User $user, Task $task): bool
    {
        return $user->can('view', $task);
    }

    public function update(User $user, Comment $comment): bool
    {
        // Users can edit their own comments
        if ($comment->user_id === $user->id) {
            return true;
        }

        // Admins and project managers can edit any comment
        return $user->hasRole(['admin', 'project-manager']);
    }

    public function delete(User $user, Comment $comment): bool
    {
        // Users can delete their own comments
        if ($comment->user_id === $user->id) {
            return true;
        }

        // Admins and project managers can delete any comment
        return $user->hasRole(['admin', 'project-manager']);
    }

    public function restore(User $user, Comment $comment): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Comment $comment): bool
    {
        return $user->hasRole('admin');
    }
}
