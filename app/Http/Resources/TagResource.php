<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tag */
class TagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'hex_color' => $this->hex_color, // Accessor that ensures # prefix
            'project_id' => $this->project_id,
            'organisation_id' => $this->organisation_id,
            'is_system' => $this->is_system,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($request->user() && $request->user()->hasPermission('delete', $this->resource), $this->deleted_at),

            // Relationships
            'project' => new ProjectResource($this->whenLoaded('project')),
            'organisation' => new OrganisationResource($this->whenLoaded('organisation')),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),

            // Conditionally load tasks if they are loaded and the request includes them
            'tasks' => $this->when(
                $this->relationLoaded('tasks') && $request->has('include_tasks'),
                TaskResource::collection($this->tasks)
            ),

            // Permission flags for the currently authenticated user
            'can' => $this->when($request->user(), [
                'update' => $request->user() ? $request->user()->hasPermission('update', $this->resource) : false,
                'delete' => $request->user() ? $request->user()->hasPermission('delete', $this->resource) : false,
            ]),
        ];
    }
}
