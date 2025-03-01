<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view projects');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('view project') ||
            $project->users->contains($user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create project');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('update project');
    }

    public function delete(User $user, Project $project): bool
    {
        // Higher permission required for deletion
        return $user->hasPermissionTo('delete project');
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('delete project');
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('delete project');
    }

    public function manageUsers(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('update project');
    }
}
