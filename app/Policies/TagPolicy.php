<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class TagPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tags.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Anyone authenticated can view tags
        return true;
    }

    /**
     * Determine whether the user can view the tag.
     *
     * @param User $user
     * @param Tag $tag
     * @return Response|bool
     */
    public function view(User $user, Tag $tag): Response|bool
    {
        // User can view tag if they can view the project
        return $user->hasPermission('tag.view', $tag->organisation_id);
    }

    /**
     * Determine whether the user can create tags.
     *
     * @param User $user
     * @param int|null $projectId
     * @return Response|bool
     */
    public function create(User $user, ?int $projectId = null): Response|bool
    {
        if (!$projectId) {
            return false;
        }

        $project = \App\Models\Project::find($projectId);

        // User can create tags if they can update the project
        return $project && $user->hasPermission('tag.create');
    }

    /**
     * Determine whether the user can update the tag.
     *
     * @param User $user
     * @param Tag $tag
     * @return Response|bool
     */
    public function update(User $user, Tag $tag): Response|bool
    {
        if($tag->is_system) {
            // System tags cannot be updated
            return Response::deny('System tags cannot be updated.');
        }
        // User can update tag if they can update the project
        return $user->hasPermission('tag.update', $tag->organisation_id);
    }

    /**
     * Determine whether the user can delete the tag.
     *
     * @param User $user
     * @param Tag $tag
     * @return Response|bool
     */
    public function delete(User $user, Tag $tag): Response|bool
    {

        if($tag->is_system) {
            // System tags cannot be deleted
            return Response::deny('System tags cannot be deleted.');
        }

        if ($tag->tasks()->count() > 0) {
            return Response::deny('Cannot delete tag that is in use by tasks.');
        }

        return $user->hasPermission('tag.delete', $tag->organisation_id);
    }

    /**
     * Determine whether the user can restore the tag.
     *
     * @param User $user
     * @param Tag $tag
     * @return Response|bool
     */
    public function restore(User $user, Tag $tag): Response|bool
    {
        // User can restore tag if they can update the project
        return $user->hasPermission('tag.restore', $tag->organisation_id);
    }

    /**
     * Determine whether the user can permanently delete the tag.
     *
     * @param User $user
     * @param Tag $tag
     * @return Response|bool
     */
    public function forceDelete(User $user, Tag $tag): Response|bool
    {
        if($tag->is_system) {
            // System tags cannot be permanently deleted
            return Response::deny('System tags cannot be permanently deleted.');
        }
        // Only allow permanent deletion for users with delete project permission
        return $user->hasPermission('tag.forceDelete', $tag->organisation_id);
    }
}
