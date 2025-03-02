<?php

namespace App\Http\Resources;

use App\Models\Priority;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Priority */
class PriorityResource extends JsonResource
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
            'level' => $this->level,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),

            // Add tasks relation when loaded
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),

            // Links for better HATEOAS support
            'links' => [
                'self' => route('priorities.show', $this->id),
            ],
        ];
    }
}
