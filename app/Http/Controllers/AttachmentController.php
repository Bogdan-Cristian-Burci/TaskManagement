<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

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
        $task = Task::findOrFail($validated['task_id']);

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
     * Remove the specified attachment from storage.
     */
    public function destroy(Attachment $attachment)
    {
        Gate::authorize('delete', $attachment);

        $attachment->delete();

        return response()->noContent();
    }

    /**
     * Download the attachment file.
     */
    public function download(Attachment $attachment)
    {
        Gate::authorize('view', $attachment);

        if (!Storage::exists($attachment->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::download(
            $attachment->file_path,
            $attachment->original_filename
        );
    }
}
