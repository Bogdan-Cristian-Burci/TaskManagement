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
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('manage all attachments')) {
            return true;
        }

        return null; // fall through to specific permissions
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view attachments');
    }

    /**
     * Determine if the user can view the attachment.
     */
    public function view(User $user, Attachment $attachment): Response
    {
        if ($user->can('view attachment')) {
            return Response::allow();
        }

        // Check if user owns the attachment
        if ($user->id === $attachment->user_id) {
            return Response::allow();
        }

        // Check if user is associated with the task
        if ($attachment->task && $attachment->task->users()->where('users.id', $user->id)->exists()) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view this attachment.');
    }

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
    public function update(User $user, Attachment $attachment): Response
    {
        if ($user->can('update attachment')) {
            return Response::allow();
        }

        if ($user->id === $attachment->user_id) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to update this attachment.');
    }

    /**
     * Determine if the user can delete the attachment.
     */
    public function delete(User $user, Attachment $attachment): Response
    {
        if ($user->can('delete attachment')) {
            return Response::allow();
        }

        if ($user->id === $attachment->user_id) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to delete this attachment.');
    }

    /**
     * Determine if the user can restore the attachment.
     */
    public function restore(User $user, Attachment $attachment): Response
    {
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
