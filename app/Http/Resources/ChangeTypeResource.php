<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Include related counts when loaded or requested
            'task_histories_count' => $this->when(isset($this->task_histories_count), $this->task_histories_count),

            // Add the task histories relation when loaded
            'task_histories' => TaskHistoryResource::collection($this->whenLoaded('taskHistories')),

            // Links for better HATEOAS support
            'links' => [
                'self' => route('change-types.show', $this->id),
            ],
        ];
    }
}
