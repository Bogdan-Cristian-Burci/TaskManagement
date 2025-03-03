<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'avatar' => $this->when($this->avatar, $this->avatar),
            'job_title' => $this->when($this->job_title, $this->job_title),
            'phone' => $this->when($this->phone, $this->phone),
            'bio' => $this->when($this->bio, $this->bio),
            'email_verified_at' => $this->when($this->email_verified_at, $this->email_verified_at),
            'initials' => $this->initials,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Include role information
            'roles' => $this->whenLoaded('roles', function() {
                return $this->roles->pluck('name');
            }),

            // Include permissions information
            'permissions' => $this->whenLoaded('permissions', function() {
                return $this->getAllPermissions()->pluck('name');
            }),

            // Include organisation information
            'organisation_id' => $this->when($this->organisation_id, $this->organisation_id),
            'organisation' => new OrganisationResource($this->whenLoaded('organisation')),

            // Check if the user is authenticated via social login
            'social_auth' => $this->when($this->provider, function() {
                return [
                    'provider' => $this->provider,
                ];
            }),

            // Include counts when requested
            'teams_count' => $this->when($request->has('with_counts'), function() {
                return $this->teams()->count();
            }),
            'tasks_count' => $this->when($request->has('with_counts'), function() {
                return $this->tasksResponsibleFor()->count();
            }),
            'projects_count' => $this->when($request->has('with_counts'), function() {
                return $this->projects()->count();
            }),

            // Include related resources when loaded
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasksResponsibleFor')),
            'organisations' => OrganisationResource::collection($this->whenLoaded('organisations')),

            // Add permission flags for the currently authenticated user
            'can' => [
                'update' => $request->user() ? $request->user()->can('update', $this->resource) : false,
                'delete' => $request->user() ? $request->user()->can('delete', $this->resource) : false,
                'manage_roles' => $request->user() ? $request->user()->can('manageRoles', $this->resource) : false,
            ],

            // HATEOAS links
            'links' => [
                'self' => route('users.show', $this->id),
                'teams' => route('users.teams', $this->id),
                'tasks' => route('users.tasks', $this->id),
                'projects' => route('users.projects', $this->id),
            ],
        ];
    }
}
