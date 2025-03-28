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
        return $user->hasPermission('project.viewAny');
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
        if ($user->hasPermission('project.view',$project->organisation_id)) {
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
        if ($user->hasPermission('project.create')) {
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
        if ($user->hasPermission('project.update',$project->organisation_id)) {
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
        if ($user->hasPermission('project.delete',$project->organisation_id)) {
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
        if (!$user->hasPermission('project.forceDelete',$project->organisation_id)) {
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
        if ($user->hasPermission('manage-projects',$project->organisation_id)) {
            return true;
        }

        return Response::deny('You do not have permission to manage users for this project.');
    }
}
