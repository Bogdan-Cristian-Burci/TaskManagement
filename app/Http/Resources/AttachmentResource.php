<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_url' => $this->file_url,
            'file_size' => $this->file_size,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'task_id' => $this->task_id,
            'user_id' => $this->user_id,

            'task' => new TaskResource($this->whenLoaded('task')),
            'user' => new UserResource($this->whenLoaded('user')),

            // Add links for better HATEOAS support
            'links' => [
                'self' => route('attachments.show', ['attachment' => $this->id]),
                'download' => $this->file_url,
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-File-Size', $this->file_size);
    }
}
