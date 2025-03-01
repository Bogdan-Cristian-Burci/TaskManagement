<?php

namespace App\Http\Resources;

use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Status */
class StatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_complete' => $this->is_complete,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Add links for better HATEOAS support
            'links' => [
                'self' => route('statuses.show', ['status' => $this->id]),
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Status-Complete', $this->is_complete);
    }
}
