<?php

namespace App\Http\Resources;

use App\Models\StatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StatusTransition */
class StatusTransitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'from_status_id' => $this->from_status_id,
            'to_status_id' => $this->to_status_id,
            'board_id' => $this->board_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Include related resources when loaded
            'from_status' => new StatusResource($this->whenLoaded('fromStatus')),
            'to_status' => new StatusResource($this->whenLoaded('toStatus')),
            'board' => new BoardResource($this->whenLoaded('board')),

            // Links for better HATEOAS support
            'links' => [
                'self' => route('status-transitions.show', $this->id),
            ],
        ];
    }
}
