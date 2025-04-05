<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    /**
     * Get all attachments for a specific task.
     */
    public function getByTask(Task $task)
    {
        Gate::authorize('view', $task);

        $attachments = $task->attachments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return AttachmentResource::collection($attachments);
    }

    /**
     * Store a newly created attachment in storage.
     */
    public function store(AttachmentRequest $request)
    {
        $validated = $request->validated();

        // Authorization is handled in AttachmentRequest

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('attachments', $filename, 'public');

        $attachment = new Attachment([
            'task_id' => $validated['task_id'],
            'user_id' => auth()->id(),
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'description' => $validated['description'] ?? null,
        ]);

        $attachment->save();

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified attachment.
     */
    public function show(Attachment $attachment)
    {
        Gate::authorize('view', $attachment);

        return new AttachmentResource($attachment->load('user'));
    }

    /**
     * Update the specified attachment in storage.
     */
    public function update(AttachmentRequest $request, Attachment $attachment)
    {
        $validated = $request->validated();

        // Authorization is handled in AttachmentRequest

        $attachment->update([
            'description' => $validated['description'] ?? null,
        ]);

        return new AttachmentResource($attachment);
    }

    /**
     * Remove the specified attachment.
     *
     * @param Request $request
     * @param Attachment $attachment
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        try {

            // Get file path information before deletion
            $disk = $attachment->disk ?? 'public';
            $storage = Storage::disk($disk);
            $originalPath = $attachment->file_path;

            $filename = basename($originalPath);

            // Create a trash directory path with preservation of subdirectories
            $relativePath = dirname($originalPath);
            $trashPath = 'trash/' . $relativePath;


            // Ensure trash directory exists
            if (!$storage->exists($trashPath)) {
                $result = $storage->makeDirectory($trashPath, 0755, true);
            }

            // Move the file to trash with the same filename
            if ($storage->exists($originalPath)) {

                $storage->move($originalPath, $trashPath . '/' . $filename);

                // Store the trash path in metadata for potential restoration
                $metadata = [
                    'original_path' => $originalPath,
                    'trash_path' => $trashPath . '/' . $filename,
                    'deleted_by' => auth()->user()->name,
                    'deleted_at' => now()->toIso8601String()
                ];

                // Set metadata and save explicitly
                $attachment->metadata = $metadata;
                $attachment->save();
            } else {
                \Log::warning('Original file not found', ['path' => $originalPath]);
            }

            // Soft delete the attachment record
            $attachment->delete();

            return response()->json([
                'message' => 'Attachment deleted successfully.',
                'attachment_id' => $attachment->id
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error('Error deleting attachment: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to delete attachment: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted attachment.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $attachment = Attachment::withTrashed()->findOrFail($id);
        $this->authorize('restore', $attachment);

        try {
            $storage = Storage::disk($attachment->disk);
            $metadata = $attachment->metadata;

            // Check if we have metadata with file paths
            if (!empty($metadata) && isset($metadata['original_path']) && isset($metadata['trash_path'])) {
                $trashPath = $metadata['trash_path'];
                $originalPath = $metadata['original_path'];

                // Move the file back to its original location
                if ($storage->exists($trashPath)) {
                    // Create directory if it doesn't exist
                    $originalDir = dirname($originalPath);
                    if (!$storage->exists($originalDir)) {
                        $storage->makeDirectory($originalDir, 0755, true);
                    }

                    // Move file back
                    $storage->move($trashPath, $originalPath);
                }
            }

            // Restore the attachment record
            $attachment->restore();

            // Clear the metadata
            $attachment->metadata = null;
            $attachment->save();

            return response()->json([
                'message' => 'Attachment restored successfully.',
                'attachment' => new AttachmentResource($attachment)
            ]);
        } catch (\Exception $e) {
            \Log::error('Error restoring attachment: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to restore attachment: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a download link for an attachment.
     *
     * @param Attachment $attachment
     * @return JsonResponse
     */
    public function download(Attachment $attachment)
    {
        $this->authorize('view', $attachment);

        // Generate a signed URL valid for 60 minutes
        $url = URL::temporarySignedRoute(
            'attachments.download.file',  // We'll create this route
            now()->addMinutes(60),
            ['attachment' => $attachment->id]
        );

        try {
            $disk = $attachment->disk ?? 'public';
            $path = $attachment->file_path;

            // Determine if file exists (try both spelling variations)
            $fileExists = Storage::disk($disk)->exists($path);

            if (!$fileExists) {
                return response()->json([
                    'error' => 'File not found',
                    'message' => 'The requested attachment file could not be found.'
                ], 404);
            }

            $size = Storage::disk($disk)->size($path);

            return response()->json([
                'url' => $url,
                'expires_at' => now()->addMinutes(60)->toIso8601String(),
                'filename' => $attachment->name ?? basename($path),
                'mime_type' => $attachment->mime_type ?? 'application/octet-stream',
                'size' => $size
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error processing file',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actual file download handler for signed URLs.
     *
     * @param Attachment $attachment
     * @return BinaryFileResponse
     */
    public function downloadFile(Attachment $attachment)
    {
        // No auth check needed - the signed URL is the authorization

        $disk = $attachment->disk ?? 'public';
        $path = $attachment->file_path;

        // Check if file exists
        $fileExists = Storage::disk($disk)->exists($path);

        if (!$fileExists) {
            abort(404, 'File not found');
        }

        // Get the file's full path
        $filePath = Storage::disk($disk)->path($path);

        // Return file download
        return response()->download(
            $filePath,
            $attachment->name ?? basename($path),
            [
                'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            ]
        );
    }
}
