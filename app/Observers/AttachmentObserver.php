<?php

namespace App\Observers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentObserver
{
    /**
     * Handle the Attachment "created" event.
     */
    public function created(Attachment $attachment): void
    {
        // Log the attachment creation in task history
        $attachment->task->history()->create([
            'user_id' => auth()->id() ?? $attachment->user_id,
            'field_changed' => 'attachment_added',
            'new_value' => $attachment->original_filename,
            'new_data' => [
                'attachment_id' => $attachment->id,
                'filename' => $attachment->filename,
                'original_filename' => $attachment->original_filename,
                'file_size' => $attachment->file_size,
                'file_type' => $attachment->file_type,
            ],
        ]);
    }

    /**
     * Handle the Attachment "updated" event.
     */
    public function updated(Attachment $attachment): void
    {
        // Get the original and current description values
        $oldDescription = $attachment->getOriginal('description');
        $newDescription = $attachment->description;

        // If description was updated, log it in task history
        if ($oldDescription !== $newDescription) {
            $attachment->task->history()->create([
                'user_id' => auth()->id() ?? $attachment->user_id,
                'field_changed' => 'attachment_updated',
                'old_value' => $oldDescription,
                'new_value' => $newDescription,
                'old_data' => [
                    'attachment_id' => $attachment->id,
                    'description' => $oldDescription,
                ],
                'new_data' => [
                    'attachment_id' => $attachment->id,
                    'description' => $newDescription,
                ],
            ]);
        }
    }

    /**
     * Handle the Attachment "deleted" event.
     */
    public function deleted(Attachment $attachment): void
    {
        // Log the deletion in task history
        $attachment->task->history()->create([
            'user_id' => auth()->id() ?? $attachment->user_id,
            'field_changed' => 'attachment_removed',
            'old_value' => $attachment->original_filename,
            'old_data' => [
                'attachment_id' => $attachment->id,
                'filename' => $attachment->filename,
                'original_filename' => $attachment->original_filename,
            ],
        ]);
    }

    /**
     * Handle the Attachment "restored" event.
     */
    public function restored(Attachment $attachment): void
    {
        // Log the restoration in task history
        $attachment->task->history()->create([
            'user_id' => auth()->id() ?? $attachment->user_id,
            'field_changed' => 'attachment_restored',
            'new_value' => $attachment->original_filename,
            'new_data' => [
                'attachment_id' => $attachment->id,
                'filename' => $attachment->filename,
                'original_filename' => $attachment->original_filename,
            ],
        ]);
    }
}
