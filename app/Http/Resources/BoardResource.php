<?php

namespace App\Http\Resources;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Board */
class BoardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'project_id' => $this->project_id,
            'board_type_id' => $this->board_type_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'project' => new ProjectResource($this->whenLoaded('project')),
            'board_type' => new BoardTypeResource($this->whenLoaded('boardType')),
            'columns' => BoardColumnResource::collection($this->whenLoaded('columns')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
