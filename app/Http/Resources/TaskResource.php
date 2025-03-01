<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'task_number' => $this->task_number,
            'parent_task_id' => $this->parent_task_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'projects_id' => $this->projects_id,
            'boards_id' => $this->boards_id,
            'status_id' => $this->status_id,
            'priority_id' => $this->priority_id,
            'task_type_id' => $this->task_type_id,
            'responsible_id' => $this->responsible_id,
            'reporter_id' => $this->reporter_id,

            'projects' => new ProjectResource($this->whenLoaded('projects')),
            'boards' => new BoardResource($this->whenLoaded('boards')),
        ];
    }
}
