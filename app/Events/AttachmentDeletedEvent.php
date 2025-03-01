<?php

namespace App\Events;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttachmentDeletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The attachment instance that was deleted.
     *
     * @var Attachment
     */
    public Attachment $attachment;

    /**
     * The user who deleted the attachment.
     *
     * @var User
     */
    public User $causer;

    /**
     * Create a new event instance.
     *
     * @param Attachment $attachment
     * @param User $causer
     * @return void
     */
    public function __construct(Attachment $attachment, User $causer)
    {
        $this->attachment = $attachment;
        $this->causer = $causer;
    }
}
