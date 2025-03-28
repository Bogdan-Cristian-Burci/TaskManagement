<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view projects');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Project $project
     * @return Response|bool
     */
    public function view(User $user, Project $project): Response|bool
    {
        // Super admins and users with global permissions can view any project
        if ($user->hasPermission('view project',$project->organisation_id)) {
            return true;
        }

        // Project members can view the project
        if ($project->users->contains($user->id)) {
            return true;
        }

        // Team members can view the project
        if ($user->teams->contains($project->team_id)) {
            return true;
        }

        // Organisation members can view the project if they have the right organisation role
        $organisationUser = $user->organisations()
            ->where('organisations.id', $project->organisation_id)
            ->first();

        if ($organisationUser && in_array($organisationUser->pivot->role, ['owner', 'admin'])) {
            return true;
        }

        return Response::deny('You do not have access to this project.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        // Check for the specific permission
        if ($user->hasPermission('create project')) {
            return true;
        }

        // Check if the user has organisation-level permissions to create projects
        $organisationRoles = $user->organisations()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->count();

        if ($organisationRoles > 0) {
            return true;
        }

        return Response::deny('You do not have permission to create projects.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Project $project
     * @return Response|bool
     */
    public function update(User $user, Project $project): Response|bool
    {
        // Super admins and users with global permissions can update any project
        if ($user->hasPermission('update project',$project->organisation_id)) {
            return true;
        }

        // Organisation admins and owners can update projects in their organisation
        $organisationRole = $user->organisations()
            ->where('organisations.id', $project->organisation_id)
            ->first()?->pivot->role;

        if ($organisationRole && in_array($organisationRole, ['owner', 'admin'])) {
            return true;
        }

        return Response::deny('You do not have permission to update this project.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Project $project
     * @return Response|bool
     */
    public function delete(User $user, Project $project): Response|bool
    {
        // Higher permission required for deletion
        if ($user->hasPermission('delete project',$project->organisation_id)) {
            return true;
        }

        // Team leads can delete the team's projects
        $teamRole = $user->teams()
            ->where('teams.id', $project->team_id)
            ->first()?->pivot->role;

        if ($teamRole === 'lead') {
            return true;
        }

        // Organisation admins and owners can delete projects in their organisation
        $organisationRole = $user->organisations()
            ->where('organisations.id', $project->organisation_id)
            ->first()?->pivot->role;

        if ($organisationRole && in_array($organisationRole, ['owner', 'admin'])) {
            return true;
        }

        return Response::deny('You do not have permission to delete this project.');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param Project $project
     * @return Response|bool
     */
    public function restore(User $user, Project $project): Response|bool
    {
        return $this->delete($user, $project);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param Project $project
     * @return Response|bool
     */
    public function forceDelete(User $user, Project $project): Response|bool
    {
        if (!$user->hasPermission('delete project',$project->organisation_id)) {
            return Response::deny('You do not have permission to permanently delete projects.');
        }
        return true;
    }

    /**
     * Determine whether the user can manage users for the model.
     *
     * @param User $user
     * @param Project $project
     * @return Response|bool
     */
    public function manageUsers(User $user, Project $project): Response|bool
    {
        // Super admins and users with global permissions can manage users for any project
        if ($user->hasPermission('update project',$project->organisation_id)) {
            return true;
        }

        return Response::deny('You do not have permission to manage users for this project.');
    }
}
