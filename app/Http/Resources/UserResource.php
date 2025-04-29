<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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

            // Add roles with their full data including permissions
            'roles' => $this->when(
                $organisationId,
                function() use ($user, $organisationId, $request) {
                    $roles = $user->getOrganisationRoles($organisationId);

                    // Always return role objects with consistent structure
                    return $roles->map(function($role) use ($request) {
                        $roleData = [
                            'id' => $role->id,
                            'name' => $role->template ? $role->template->name : $role->name,
                            'display_name' => $role->template ? $role->template->display_name : $role->display_name,
                            'description' => $role->template ? $role->template->description : $role->description,
                            'level' => $role->level,
                            'is_system' => $role->is_system ?? false
                        ];
                        
                        // Only add permissions array if specifically requested
                        if ($request->has('include') && in_array('permissions', explode(',', $request->input('include')))) {
                            // Get all permissions from the system
                            $allPermissions = \App\Models\Permission::all();
                            
                            // Get permissions assigned to this role
                            $rolePermissions = DB::table('template_has_permissions')
                                ->where('role_template_id', $role->template_id)
                                ->pluck('permission_id')
                                ->toArray();
                                
                            $roleData['permissions'] = $allPermissions->map(function($permission) use ($rolePermissions) {
                                return [
                                    'id' => $permission->id,
                                    'name' => $permission->name,
                                    'display_name' => $permission->display_name,
                                    'category' => $permission->category,
                                    'description' => $permission->description,
                                    'is_active' => in_array($permission->id, $rolePermissions)
                                ];
                            })->values();
                        }
                        
                        return $roleData;
                    })->values();
                }
            ),

            'permissions' => $this->when(
                $request->has('include') && in_array('permissions', explode(',', $request->input('include'))),
                function() use ($user, $organisationId) {
                    return $user->getOrganisationPermissionsAttribute();
                }
            ),

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
