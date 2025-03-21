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
                // Using the new method to get user's role in organization
                $role = $request->user()->organisationRole($this->resource);
                return $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                    'level' => $role->getLevel(),
                    'template' => $role->template ? $role->template->name : null
                ] : null;
            }),

            // User permissions for this organization
            'can' => [
                'update' => $request->user() ? $request->user()->hasPermission('organisation.update', $this->id) : false,
                'delete' => $request->user() ? $request->user()->hasPermission('organisation.delete', $this->id) : false,
                'invite_users' => $request->user() ? $request->user()->hasPermission('organisation.inviteUser', $this->id) : false,
                'manage_settings' => $request->user() ? $request->user()->hasPermission('organisation.manageSettings', $this->id) : false,
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
