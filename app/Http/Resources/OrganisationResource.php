<?php

namespace App\Http\Resources;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organisation */
class OrganisationResource extends JsonResource
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
            'slug' => $this->slug,
            'unique_id' => $this->unique_id,
            'description' => $this->description,
            'logo' => $this->logo,
            'address' => $this->address,
            'website' => $this->website,
            'created_by' => $this->created_by,
            'owner_id' => $this->owner_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Get the currently authenticated user's role in this organization
            'user_role' => $this->when($request->user(), function() use ($request) {
                return $this->getUserRole($request->user());
            }),

            // User permissions for this organization
            'can' => [
                'update' => $request->user() ? $request->user()->hasPermission('update', $this->resource) : false,
                'delete' => $request->user() ? $request->user()->hasPermission('delete', $this->resource) : false,
                'manage_members' => $request->user() ? $request->user()->hasPermission('manageMembers', $this->resource) : false,
                'change_owner' => $request->user() ? $request->user()->hasPermission('changeOwner', $this->resource) : false,
            ],

            // Include counts when requested
            'members_count' => $this->when($request->has('with_counts'), function() {
                return $this->users()->count();
            }),
            'teams_count' => $this->when($request->has('with_counts'), function() {
                return $this->teams()->count();
            }),
            'projects_count' => $this->when($request->has('with_counts'), function() {
                return $this->projects()->count();
            }),

            // Include related resources when loaded
            'owner' => new UserResource($this->whenLoaded('owner')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),

            // Add HATEOAS links
            'links' => [
                'self' => route('organisations.show', $this->id),
                'teams' => route('organisations.teams.index', $this->id),
                'projects' => route('organisations.projects.index', $this->id),
                'members' => route('organisations.users.index', $this->id),
            ],
        ];
    }
}
