<?php

namespace App\Http\Resources;

use App\Models\Organisation;
use App\Models\User;
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

            // Is this the active organisation for the current user?
            'is_active' => $this->when($request->user(), function() use ($request) {
                $contextUser = $request->user();

                // If we're in a pivot context (within UserResource)
                if ($this->pivot && isset($this->pivot->user_id)) {
                    $contextUser = User::find($this->pivot->user_id);
                }

                return $contextUser && $contextUser->organisation_id === $this->id;
            }),

            // Fix user_role to include pivot roles
            'user_role' => $this->when(true, function() use ($request) {
                // Get the appropriate user based on context
                $contextUser = $request->user();
                $organisationId = $this->id;

                // If we're in a pivot context (within UserResource)
                if ($this->pivot && isset($this->pivot->user_id)) {
                    $contextUser = User::find($this->pivot->user_id);
                }

                if ($contextUser) {
                    // First check for role in pivot table
                    $pivotRole = $contextUser->organisations()
                        ->where('organisations.id', $organisationId)
                        ->first()?->pivot?->role;

                    if ($pivotRole) {
                        // If this is the owner in the pivot, return owner role info
                        if ($pivotRole === 'owner') {
                            return [
                                'id' => null, // No formal role ID for pivot roles
                                'name' => 'owner',
                                'level' => 100, // Highest level
                                'pivot_role' => true,
                                'template' => null
                            ];
                        } elseif ($pivotRole === 'admin') {
                            return [
                                'id' => null,
                                'name' => 'admin',
                                'level' => 75, // High level
                                'pivot_role' => true,
                                'template' => null
                            ];
                        } elseif ($pivotRole === 'member') {
                            return [
                                'id' => null,
                                'name' => 'member',
                                'level' => 25, // Standard level
                                'pivot_role' => true,
                                'template' => null
                            ];
                        }
                    }

                    // Otherwise check for formal role
                    $role = $contextUser->organisationRole($this->resource);
                    if ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->getName(),
                            'level' => $role->getLevel(),
                            'pivot_role' => false,
                            'template' => $role->template ? $role->template->name : null
                        ];
                    }
                }

                return null;
            }),

            // Fix permission checks to account for pivot roles
            'can' => $this->when(true, function() use ($request) {
                // Get the appropriate user based on context
                $contextUser = $request->user();
                $organisationId = $this->id;

                // If we're in a pivot context
                if ($this->pivot && isset($this->pivot->user_id)) {
                    $contextUser = User::find($this->pivot->user_id);
                }

                // If we have a user context, check permissions
                if ($contextUser) {
                    // Check if user is owner or admin in this organization via pivot
                    $pivotRole = $contextUser->organisations()
                        ->where('organisations.id', $organisationId)
                        ->first()?->pivot?->role;

                    $isOwnerOrAdmin = in_array($pivotRole, ['owner', 'admin']);

                    // For newly created orgs, owner should have all permissions
                    if ($isOwnerOrAdmin || $contextUser->id === $this->owner_id || $contextUser->id === $this->created_by) {
                        return [
                            'update' => true,
                            'delete' => true,
                            'invite_users' => true,
                            'manage_settings' => true,
                        ];
                    }

                    // Fall back to permission checks
                    return [
                        'update' => $contextUser->hasPermission('organisation.update', $organisationId),
                        'delete' => $contextUser->hasPermission('organisation.delete', $organisationId),
                        'invite_users' => $contextUser->hasPermission('organisation.inviteUser', $organisationId),
                        'manage_settings' => $contextUser->hasPermission('organisation.manageSettings', $organisationId),
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
