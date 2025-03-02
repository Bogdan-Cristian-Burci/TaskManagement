<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'content' => $this->content,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_edited' => $this->created_at->ne($this->updated_at),
            'excerpt' => $this->getExcerpt(),
            'is_reply' => $this->isReply(),

            // Include related resources when loaded
            'user' => new UserResource($this->whenLoaded('user')),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'reply_count' => $this->when(!$this->isReply(), function() {
                return $this->replies()->count();
            }),

            // Add capabilities based on current user's permissions
            'can_edit' => $request->user() ? $request->user()->can('update', $this->resource) : false,
            'can_delete' => $request->user() ? $request->user()->can('delete', $this->resource) : false,

            // Links for better HATEOAS support
            'links' => [
                'self' => route('comments.show', $this->id),
                'task' => route('tasks.show', $this->task_id),
            ],
        ];

        return $data;
    }
}
