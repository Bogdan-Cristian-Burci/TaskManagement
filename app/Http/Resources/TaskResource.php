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
            'estimated_hours' => $this->estimated_hours,
            'spent_hours' => $this->spent_hours,
            'start_date' => $this->start_date,
            'due_date' => $this->due_date,
            'position' => $this->position,
            'is_overdue' => $this->isOverdue(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'project_id' => $this->project_id,
            'board_id' => $this->board_id,
            'board_column_id' => $this->board_column_id,
            'status_id' => $this->status_id,
            'priority_id' => $this->priority_id,
            'task_type_id' => $this->task_type_id,
            'responsible_id' => $this->responsible_id,
            'reporter_id' => $this->reporter_id,

            'project' => new ProjectResource($this->whenLoaded('project')),
            'board' => new BoardResource($this->whenLoaded('board')),
            'board_column' => new BoardColumnResource($this->whenLoaded('board_column')),
            'status' => new StatusResource($this->whenLoaded('status')),
            'priority' => new PriorityResource($this->whenLoaded('priority')),
            'task_type' => new TaskTypeResource($this->whenLoaded('taskType')),
            'responsible' => new UserResource($this->whenLoaded('responsible')),
            'reporter' => new UserResource($this->whenLoaded('reporter')),
            'parent_task' => new TaskResource($this->whenLoaded('parentTask')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'history' => TaskHistoryResource::collection($this->whenLoaded('history')),
        ];
    }
}
