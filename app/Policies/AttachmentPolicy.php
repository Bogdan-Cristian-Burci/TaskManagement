<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    use HandlesAuthorization;


    /**
     * Determine whether the user can view any attachments.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view attachments');
    }

    /**
     * Determine if the user can view the attachment.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        // Users can view attachments if they can view the associated task
        return $user->can('view', $attachment->task);
    }

    /**
     * Determine whether the user can create attachments.
     */
    public function create(User $user): Response
    {
        if ($user->can('create attachment')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to create attachments.');
    }
    /**
     * Determine if the user can update the attachment.
     */
    public function update(User $user, Attachment $attachment): bool
    {
        // Users can update attachments if they uploaded them or can update the associated task
        return $user->id === $attachment->user_id || $user->can('update', $attachment->task);
    }

    /**
     * Determine if the user can delete the attachment.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        // Users can delete attachments if they uploaded them or can update the associated task
        return $user->id === $attachment->user_id || $user->can('update', $attachment->task);
    }

    /**
     * Determine if the user can restore the attachment.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        // Same rules as delete
        return $this->delete($user, $attachment);
    }

    /**
     * Determine if the user can permanently delete the attachment.
     */
    public function forceDelete(User $user, Attachment $attachment): Response
    {
        if ($user->can('force delete attachment')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to permanently delete attachments.');
    }
}
