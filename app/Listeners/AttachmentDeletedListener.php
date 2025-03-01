<?php

namespace App\Listeners;

use App\Events\AttachmentDeletedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AttachmentDeletedListener implements ShouldQueue
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
     * @param  \App\Events\AttachmentDeletedEvent  $event
     * @return void
     */
    public function handle(AttachmentDeletedEvent $event)
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
            ->log('attachment_deleted');

        // Add any additional actions here:
        // - Clean up related resources
        // - Delete physical files if needed
        // - Update related statistics
        // - etc.
    }
}
