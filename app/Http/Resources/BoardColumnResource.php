<?php

namespace App\Http\Resources;

use App\Models\BoardColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BoardColumn */
class BoardColumnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'board_id' => $this->board_id,
            'position' => $this->position,
            'color' => $this->color,
            'wip_limit' => $this->wip_limit,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'board' => new BoardResource($this->whenLoaded('board')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
