<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttachmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view attachments');
    }

    public function view(User $user, Attachment $attachment): bool
    {
        if ($user->can('view attachment')) {
            return true;
        }

        // User can view their own attachments
        return $user->id === $attachment->user_id;
    }

    public function create(User $user): bool
    {
        return $user->can('create attachment');
    }

    public function update(User $user, Attachment $attachment): bool
    {
        if ($user->can('update attachment')) {
            return true;
        }

        return $user->id === $attachment->user_id;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->can('delete attachment')) {
            return true;
        }

        return $user->id === $attachment->user_id;
    }

    public function restore(User $user, Attachment $attachment): bool
    {
        if ($user->can('delete attachment')) {
            return true;
        }

        return $user->id === $attachment->user_id;
    }

    public function forceDelete(User $user, Attachment $attachment): bool
    {
        if ($user->can('delete attachment')) {
            return true;
        }

        return $user->id === $attachment->user_id;
    }
}
