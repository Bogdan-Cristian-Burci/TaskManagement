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
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'field_changed' => $this->field_changed,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
