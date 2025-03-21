<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'key' => $this->key,
            'organisation_id' => $this->organisation_id,
            'organisation' => new OrganisationResource($this->whenLoaded('organisation')),
            'team_id' => $this->team_id,
            'team' => new TeamResource($this->whenLoaded('team')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'boards_count' => $this->when(isset($this->boards_count), $this->boards_count),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
