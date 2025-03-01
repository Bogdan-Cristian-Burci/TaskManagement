<?php

namespace App\Http\Resources;

use App\Models\TaskHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskHistory */
class TaskHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'task_id' => $this->task_id,
            'changed_by' => $this->changed_by,
            'change_type_id' => $this->change_type_id,

            'task' => new TaskResource($this->whenLoaded('task')),
        ];
    }
}
