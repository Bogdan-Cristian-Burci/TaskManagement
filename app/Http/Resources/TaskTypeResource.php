<?php

namespace App\Http\Resources;

use App\Models\TaskType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskType */
class TaskTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tasks_count' => $this->tasks_count,

            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),

            // Add links for better HATEOAS support
            'links' => [
                'self' => route('task-types.show', ['task_type' => $this->id]),
                'tasks' => route('task-types.index', ['task_type' => $this->id]),
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Tasks-Count', $this->tasks_count);
    }
}
