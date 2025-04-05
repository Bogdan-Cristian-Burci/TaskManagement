<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupSoftDeletedAttachmentsCommand extends Command
{
    protected $signature = 'attachments:cleanup {--days=30 : Days to keep soft-deleted attachments}';
    protected $description = 'Clean up files from attachments that have been soft-deleted for more than the specified number of days';

    public function handle(): int
    {
        $days = $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Finding attachments soft-deleted before {$cutoffDate}...");

        // Get soft-deleted attachments older than the cutoff date
        $attachments = Attachment::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->get();

        $this->info("Found {$attachments->count()} attachments to process.");

        $deleted = 0;
        $errors = 0;

        foreach ($attachments as $attachment) {
            $storage = Storage::disk($attachment->disk);
            $path = $attachment->path;

            // Check if the attachment has a trash path in metadata
            $trashPath = null;
            if (!empty($attachment->metadata) && isset($attachment->metadata['trash_path'])) {
                $trashPath = $attachment->metadata['trash_path'];
            }

            try {
                // Try deleting from trash first
                if ($trashPath && $storage->exists($trashPath)) {
                    $storage->delete($trashPath);
                    $deleted++;
                }
                // Otherwise delete from original path
                else if ($storage->exists($path)) {
                    $storage->delete($path);
                    $deleted++;
                }

                // Permanently delete the record
                $attachment->forceDelete();

            } catch (\Exception $e) {
                $this->error("Error deleting attachment {$attachment->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Completed: {$deleted} files deleted, {$errors} errors encountered.");

        return 0;
    }
}
