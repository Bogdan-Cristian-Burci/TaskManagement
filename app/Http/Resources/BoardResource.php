<?php

namespace App\Http\Resources;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Board */
class BoardResource extends JsonResource
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
            'description' => $this->description,
            'type' => $this->type,
            'is_archived' => $this->when(isset($this->is_archived), $this->is_archived, false),
            'project_id' => $this->project_id,
            'board_type_id' => $this->board_type_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Include relationships when loaded
            'project' => new ProjectResource($this->whenLoaded('project')),
            'board_type' => new BoardTypeResource($this->whenLoaded('boardType')),
            'columns' => BoardColumnResource::collection($this->whenLoaded('columns')),

            // Include counts when requested
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'columns_count' => $this->when(isset($this->columns_count), $this->columns_count),

            'active_sprint' => new SprintResource($this->whenLoaded('activeSprint')),
            'sprints_count' => $this->when($request->has('with_counts'), function() {
                return $this->sprints()->count();
            }),

            // Include computed attributes when requested
            'completion_percentage' => $this->when($request->has('with_stats'), $this->completion_percentage),

            // Include permissions for current user
            'can' => $this->when($request->user(), [
                'update' => $request->user() ? $request->user()->hasPermission('update', $this->resource) : false,
                'delete' => $request->user() ? $request->user()->hasPermission('delete', $this->resource) : false,
                'archive' => $request->user() ? $request->user()->hasPermission('archive', $this->resource) : false,
                'duplicate' => $request->user() ? $request->user()->hasPermission('duplicate', $this->resource) : false,
            ]),

            // HATEOAS links
            'links' => [
                'self' => route('boards.show', $this->id),
                'project' => route('projects.show', $this->project_id),
                'columns' => route('boards.columns.index', $this->id),
                'tasks' => route('boards.tasks.index', $this->id),
                'sprints' => route('boards.sprints.index', $this->id),
            ],
        ];
    }
}
