<?php

namespace App\Http\Resources;

use App\Models\Sprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sprint */
class SprintResource extends JsonResource
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
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'goal' => $this->goal,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at && $request->user() && $request->user()->can('delete', $this->resource), $this->deleted_at),

            // Board relationship
            'board_id' => $this->board_id,
            'board' => new BoardResource($this->whenLoaded('board')),

            // Tasks relationship
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'completed_tasks_count' => $this->when(isset($this->completed_tasks_count), $this->completed_tasks_count),

            // Calculated properties
            'progress' => $this->when($request->has('with_stats'), $this->progress),
            'days_remaining' => $this->when($request->has('with_stats'), $this->days_remaining),
            'duration' => $this->when($request->has('with_stats'), $this->duration),
            'is_active' => $this->when($request->has('with_stats'), $this->is_active),
            'is_completed' => $this->when($request->has('with_stats'), $this->is_completed),
            'is_overdue' => $this->when($request->has('with_stats'), $this->is_overdue),

            // Permission flags for the currently authenticated user
            'can' => $this->when($request->user(), [
                'update' => $request->user() ? $request->user()->can('update', $this->resource) : false,
                'delete' => $request->user() ? $request->user()->can('delete', $this->resource) : false,
                'start' => $request->user() ? $request->user()->can('start', $this->resource) : false,
                'complete' => $request->user() ? $request->user()->can('complete', $this->resource) : false,
                'manage_tasks' => $request->user() ? $request->user()->can('manageTasks', $this->resource) : false,
            ]),

            // HATEOAS links
            'links' => [
                'self' => route('sprints.show', $this->id),
                'board' => route('boards.show', $this->board_id),
                'tasks' => route('sprints.tasks.index', $this->id),
            ],
        ];
    }
}
