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
            'board_templates' => $this->when($this->relationLoaded('boards'), function() {
                $templates = collect();
                foreach ($this->boards as $board) {
                    if ($board->relationLoaded('boardType') && $board->boardType && $board->boardType->template_id) {
                        $template = \App\Models\BoardTemplate::withoutGlobalScopes()->find($board->boardType->template_id);
                        if ($template) {
                            $templates->push(new BoardTemplateResource($template));
                        }
                    }
                }
                return $templates->unique('id')->values();
            }),
            'boards_count' => $this->when(isset($this->boards_count), $this->boards_count),
            'tasks_count' => $this->when(isset($this->tasks_count), $this->tasks_count),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null,
            'updated_at' => $this->updated_at,
        ];
    }
}
