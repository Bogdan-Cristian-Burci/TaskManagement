<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    use AuthorizesRequests;
    public function __construct()
    {
        $this->authorizeResource(Attachment::class, 'attachment');
    }

    public function index(Request $request)
    {
        $query = Attachment::query();

        $query->with(['user', 'task']);

        // Add filtering if needed
        if ($request->has('mime_type')) {
            $query->where('mime_type', $request->mime_type);
        }

        if ($request->has('task_id')) {
            $query->where('task_id', $request->task_id);
        }

        // Add sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $attachments = $query->paginate($request->get('per_page', 15));

        return AttachmentResource::collection($attachments);
    }

    public function store(AttachmentRequest $request)
    {
        return new AttachmentResource(Attachment::create($request->validated()));
    }

    public function show(Attachment $attachment)
    {
        return new AttachmentResource($attachment->load(['user', 'task']));
    }

    public function update(AttachmentRequest $request, Attachment $attachment)
    {
        $attachment->update($request->validated());

        return new AttachmentResource($attachment);
    }

    public function destroy(Attachment $attachment)
    {
        $attachment->delete();

        return response()->noContent(); // 204 No Content
    }
}
