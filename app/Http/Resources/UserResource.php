<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
        // Get roles directly from the database to avoid issues
        $roleNames = [];

        if ($this->organisation_id) {
            $roleNames = \DB::table('roles')
                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $this->id)
                ->where('model_has_roles.model_type', get_class($this))
                ->where('model_has_roles.organisation_id', $this->organisation_id)
                ->pluck('roles.name')
                ->toArray();
        }

        // Check if user has admin role
        $isAdmin = in_array('admin', $roleNames);

        // Get authenticated user ID
        $authUserId = $request->user() ? $request->user()->id : null;

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

            // Always include roles
            'roles' => $roleNames,

            // Include permissions information
            'permissions' => $this->getAllPermissions()->pluck('name')->toArray(),

            // Include organisation information
            'organisation_id' => $this->when($this->organisation_id, $this->organisation_id),
            'organisation' => new OrganisationResource($this->whenLoaded('organisation')),

            // Social auth info - if applicable
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

            // Use direct role check for permissions based on role names
            'can' => [
                'update' => $isAdmin || ($authUserId && $authUserId === $this->id),
                'delete' => $isAdmin,
                'manage_roles' => $isAdmin,
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
