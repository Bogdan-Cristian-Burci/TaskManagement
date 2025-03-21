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

            // TO THIS:
            'user_role' => $this->when(true, function() use ($request) {
                // Detect if we're in a UserResource context by checking the pivot
                if ($this->pivot) {
                    // If we have pivot data, this organization belongs to a specific user
                    $userId = $this->pivot->user_id;
                    $user = \App\Models\User::find($userId);

                    if ($user) {
                        $role = $user->organisationRole($this->resource);
                        return $role ? [
                            'id' => $role->id,
                            'name' => $role->getName(),
                            'level' => $role->getLevel(),
                            'template' => $role->template ? $role->template->name : null
                        ] : null;
                    }
                }

                // Fall back to current authenticated user if not in a pivot context
                if ($request->user()) {
                    $role = $request->user()->organisationRole($this->resource);
                    return $role ? [
                        'id' => $role->id,
                        'name' => $role->getName(),
                        'level' => $role->getLevel(),
                        'template' => $role->template ? $role->template->name : null
                    ] : null;
                }

                return null;
            }),

            // User permissions for this organization
            'can' => $this->when(true, function() use ($request) {
                // Get the appropriate user based on context
                $contextUser = null;

                // If we have pivot data, this organization belongs to a specific user
                if ($this->pivot && isset($this->pivot->user_id)) {
                    $contextUser = \App\Models\User::find($this->pivot->user_id);
                }

                // Fall back to authenticated user if needed
                if (!$contextUser && $request->user()) {
                    $contextUser = $request->user();
                }

                // If we have a user context, return their permissions
                if ($contextUser) {
                    return [
                        'update' => $contextUser->hasPermission('organisation.update', $this->id),
                        'delete' => $contextUser->hasPermission('organisation.delete', $this->id),
                        'invite_users' => $contextUser->hasPermission('organisation.inviteUser', $this->id),
                        'manage_settings' => $contextUser->hasPermission('organisation.manageSettings', $this->id),
                    ];
                }

                // Default to no permissions if no user context available
                return [
                    'update' => false,
                    'delete' => false,
                    'invite_users' => false,
                    'manage_settings' => false,
                ];
            }),

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
