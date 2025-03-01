<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/** @mixin Team */
class TeamsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'organisations_id' => $this->organisations_id,

            'organisations' => new OrganisationResource($this->whenLoaded('organisations')),

            'team_lead_id' => $this->team_lead_id,
        ];
    }
}
