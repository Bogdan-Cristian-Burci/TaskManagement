<?php

namespace App\Http\Resources;

use App\Models\Sprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sprint */
class SprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'goal' => $this->goal,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'board_id' => $this->board_id,
            'status_id' => $this->status_id,

            'board' => new BoardResource($this->whenLoaded('board')),
        ];
    }
}
