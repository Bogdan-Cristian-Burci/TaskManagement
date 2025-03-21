<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     * @throws BindingResolutionException
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $organisationId = $user->organisation_id;

        // Check if user has admin role - using template names in the right organization context
        $isAdmin = false;
        if ($organisationId) {
            $isAdmin = $user->hasRoleInOrganisation('admin', $organisationId);
        }

        // Check if authenticated user is the same as current user
        $isSelf = $request->user() && $request->user()->id === $this->id;

        // Get role template names for current organization
        $roleTemplateNames = [];
        if ($organisationId) {
            $roleTemplateNames = $user->getOrganisationRoles($organisationId)
                ->map(function($role) {
                    return $role->template ? $role->template->name : $role->name;
                })
                ->filter() // Remove nulls
                ->toArray();
        }

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

            // Add roles using the new structure
            'roles' => $roleTemplateNames,

            // Get permissions using getAllPermissions method
            'permissions' => $this->when($organisationId, function() use ($user, $organisationId) {
                return $user->getAllPermissions($organisationId)
                    ->pluck('name')
                    ->toArray();
            }),

            // Add permission overrides if any
            'permission_overrides' => $this->when($organisationId, function() use ($user) {
                return $user->getPermissionOverridesAttribute();
            }),

            'organisation_id' => $this->when($this->organisation_id, $this->organisation_id),
            'organisation' => new OrganisationResource($this->whenLoaded('organisation')),

            // Social auth info
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

            // Calculate permissions based on actual permission checks
            'can' => [
                'update' => $isAdmin || $isSelf || ($organisationId && $user->hasPermission('users.update', $organisationId)),
                'delete' => $isAdmin || ($organisationId && $user->hasPermission('users.delete', $organisationId)),
                'manage_roles' => $isAdmin || ($organisationId && $user->hasPermission('roles.manage', $organisationId)),
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
