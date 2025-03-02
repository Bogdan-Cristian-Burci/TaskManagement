<?php

namespace App\Http\Resources;

use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Status */
class StatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'position' => $this->position,
            'is_default' => (bool) $this->is_default,
            'category' => $this->category,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),

            // Add tasks relation when loaded
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),

            // Links for better HATEOAS support
            'links' => [
                'self' => route('statuses.show', $this->id),
            ],
        ];
    }
}
