<?php

namespace App\Http\Controllers;

use App\Events\AttachmentCreatedEvent;
use App\Events\AttachmentDeletedEvent;
use App\Http\Requests\AttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AttachmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Attachment::class, 'attachment');
    }

    /**
     * Display a listing of attachments.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Attachment::query()
            ->with(['user', 'task']);

        // Apply filters
        $this->applyFilters($query, $request);

        // Sort results
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100); // Prevent abuse with huge page sizes
        $attachments = $query->paginate($perPage);

        return AttachmentResource::collection($attachments);
    }

    /**
     * Store a newly created attachment.
     *
     * @param AttachmentRequest $request
     * @return AttachmentResource
     */
    public function store(AttachmentRequest $request): AttachmentResource
    {
        $attachment = Attachment::create($request->validated());

        // Fire event for listeners (e.g., notifications, activity logging)
        event(new AttachmentCreatedEvent($attachment, $request->user()));

        return new AttachmentResource($attachment);

    }

    /**
     * Display the specified attachment.
     *
     * @param Attachment $attachment
     * @return AttachmentResource
     */
    public function show(Attachment $attachment): AttachmentResource
    {
        return new AttachmentResource($attachment->load(['user', 'task']));
    }

    /**
     * Update the specified attachment.
     *
     * @param AttachmentRequest $request
     * @param Attachment $attachment
     * @return AttachmentResource
     */
    public function update(AttachmentRequest $request, Attachment $attachment): AttachmentResource
    {
        $attachment->update($request->validated());

        return new AttachmentResource($attachment->fresh(['user', 'task']));
    }

    /**
     * Remove the specified attachment.
     *
     * @param Attachment $attachment
     * @return Response
     */
    public function destroy(Attachment $attachment): Response
    {
        // Store attachment info before deletion for event
        $deletedAttachment = clone $attachment;

        $attachment->delete();

        // Fire event for listeners
        event(new AttachmentDeletedEvent($deletedAttachment, auth()->user()));

        return response()->noContent(); // 204 No Content
    }

    /**
     * Apply filters to the attachment query.
     *
     * @param Builder $query
     * @param Request $request
     * @return void
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        // Filter by MIME type
        if ($request->has('mime_type')) {
            if (is_array($request->mime_type)) {
                $query->whereIn('mime_type', $request->mime_type);
            } else {
                $query->where('mime_type', $request->mime_type);
            }
        }

        // Filter by file type category (images, documents, etc.)
        if ($request->has('file_type')) {
            $fileTypePrefix = $request->file_type . '/';
            $query->where('mime_type', 'like', $fileTypePrefix . '%');
        }

        // Filter by task
        if ($request->has('task_id')) {
            $query->where('task_id', $request->task_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // Filter by file size range
        if ($request->has('min_size')) {
            $query->where('file_size', '>=', $request->min_size);
        }

        if ($request->has('max_size')) {
            $query->where('file_size', '<=', $request->max_size);
        }

        // Search by filename
        if ($request->has('search')) {
            $query->where('file_name', 'like', '%' . $request->search . '%');
        }
    }
}
