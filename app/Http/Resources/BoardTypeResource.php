<?php

namespace App\Http\Resources;

use App\Models\BoardType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BoardType */
class BoardTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'boards' => BoardResource::collection($this->whenLoaded('boards')),
        ];
    }
}
