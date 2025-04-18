<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any comments.
     *
     * @param User $user
     * @param Task $task
     * @return bool
     */
    public function viewAny(User $user, Task $task): bool
    {
        return $user->hasPermission('comment.viewAny', $task->project->organisation_id);
    }

    /**
     * Determine whether the user can view the comment.
     *
     * @param User $user
     * @param Comment $comment
     * @return bool
     */
    public function view(User $user, Comment $comment): bool
    {
        return $user->hasPermission('comment.view', $comment->task->project->organisation_id);
    }

    /**
     * Determine whether the user can create comments.
     *
     * @param User $user
     * @param Task $task
     * @return bool
     */
    public function create(User $user, Task $task): bool
    {
        return $user->hasPermission('comment.create', $task->project->organisation_id);
    }

    /**
     * Determine whether the user can update the comment.
     *
     * @param User $user
     * @param Comment $comment
     * @return bool
     */
    public function update(User $user, Comment $comment): bool
    {
        // Users can edit their own comments within a time window (e.g., 30 minutes)
        if ($comment->user_id === $user->id &&
            $comment->created_at->diffInMinutes(now()) <= 30) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the comment.
     *
     * @param User $user
     * @param Comment $comment
     * @return bool
     */
    public function delete(User $user, Comment $comment): bool
    {
        // Users can delete their own comments
        if ($comment->user_id === $user->id) {
            return true;
        }
    }

    /**
     * Determine whether the user can restore the comment.
     *
     * @param User $user
     * @param Comment $comment
     * @return bool
     */
    public function restore(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id ||
               $user->hasPermission('comment.restore', $comment->task->project->organisation_id);
    }

    /**
     * Determine whether the user can permanently delete the comment.
     *
     * @param User $user
     * @param Comment $comment
     * @return bool
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return $user->hasRole('admin');
    }
}
