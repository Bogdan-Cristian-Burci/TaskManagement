<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamResource extends JsonResource
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
            'organisation_id' => $this->organisation_id,
            'team_lead_id' => $this->team_lead_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Include member count
            'members_count' => $this->when($request->has('with_counts'), function() {
                return $this->users()->count();
            }),

            // Include project count
            'projects_count' => $this->when($request->has('with_counts'), function() {
                return $this->projects()->count();
            }),

            // Include tasks count
            'tasks_count' => $this->when($request->has('with_counts'), function() {
                return $this->tasks()->count();
            }),

            // User permissions for this team - using new permission architecture
            'can' => [
                'update' => $request->user() ? $request->user()->hasPermission('teams.update', $this->organisation_id) : false,
                'delete' => $request->user() ? $request->user()->hasPermission('teams.delete', $this->organisation_id) : false,
                'manage_members' => $request->user() ? $request->user()->hasPermission('teams.manage_members', $this->organisation_id) : false,
                'change_team_lead' => $request->user() ? $request->user()->hasPermission('teams.change_lead', $this->organisation_id) : false,
            ],

            // Check if current user is a member of this team
            'is_member' => $request->user() ? $this->hasMember($request->user()) : false,

            // Check if current user is the team lead
            'is_team_lead' => $request->user() ? $this->isTeamLead($request->user()) : false,

            // Related resources
            'organisation' => new OrganisationResource($this->whenLoaded('organisation')),
            'team_lead' => new UserResource($this->whenLoaded('teamLead')),
            'members' => UserResource::collection($this->whenLoaded('users')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),

            // Links for HATEOAS
            'links' => [
                'self' => route('teams.show', $this->id),
                'members' => route('teams.members', $this->id),
                'projects' => route('teams.projects', $this->id),
            ],
        ];
    }
}
