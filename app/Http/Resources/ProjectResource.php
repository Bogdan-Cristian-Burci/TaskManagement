<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
            'responsible_user' => new UserResource($this->whenLoaded('responsibleUser')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'boards' => BoardResource::collection($this->whenLoaded('boards')),
            'boards_count' => $this->when(isset($this->boards_count), $this->boards_count),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'media' => $this->when($request->has('include_media'), function() {
                return $this->getMedia()->map(function ($mediaItem) {
                    return [
                        'id' => $mediaItem->id,
                        'name' => $mediaItem->name,
                        'file_name' => $mediaItem->file_name,
                        'mime_type' => $mediaItem->mime_type,
                        'size' => $mediaItem->size,
                        'url' => $mediaItem->getUrl(),
                        'collection' => $mediaItem->collection_name,
                        'created_at' => $mediaItem->created_at,
                    ];
                });
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null,
            'updated_at' => $this->updated_at,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status
        ];
    }
}
