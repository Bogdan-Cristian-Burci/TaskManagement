<?php

namespace App\Listeners;

use App\Events\AttachmentCreatedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AttachmentCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\AttachmentCreatedEvent  $event
     * @return void
     */
    public function handle(AttachmentCreatedEvent $event)
    {
        // Log this activity
        activity()
            ->performedOn($event->attachment)
            ->causedBy($event->causer)
            ->withProperties([
                'file_name' => $event->attachment->file_name,
                'file_size' => $event->attachment->file_size,
                'mime_type' => $event->attachment->mime_type,
                'task_id' => $event->attachment->task_id,
            ])
            ->log('attachment_created');

        // Add any additional actions here:
        // - Send notifications
        // - Update related records
        // - Process the file
        // - etc.
    }
}
