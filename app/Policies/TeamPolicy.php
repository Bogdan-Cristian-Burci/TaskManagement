<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any teams.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can see teams they belong to
        return true;
    }

    /**
     * Determine whether the user can view the team.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function view(User $user, Team $team): bool
    {
        // Users can view teams they belong to
        return $team->hasMember($user) ||
            $user->hasPermission('team.view');
    }

    /**
     * Determine whether the user can create teams.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Only users with proper permissions can create teams
        return $user->organisations()->exists() &&
            $user->hasPermission('team.create');
    }

    /**
     * Determine whether the user can update the team.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function update(User $user, Team $team): bool
    {
        // Only team leads or admins can update teams
        return $team->isTeamLead($user) ||
            $user->hasPermission('team.update');
    }

    /**
     * Determine whether the user can delete the team.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function delete(User $user, Team $team): bool
    {
        // Only team leads or admins can delete teams
        return $team->isTeamLead($user) ||
            $user->hasPermission('team.delete');
    }

    /**
     * Determine whether the user can restore the team.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function restore(User $user, Team $team): bool
    {
        return $user->hasPermission('team.restore');
    }

    /**
     * Determine whether the user can permanently delete the team.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function forceDelete(User $user, Team $team): bool
    {
        return $user->hasPermission('team.forceDelete');
    }

    /**
     * Determine whether the user can manage members of the team.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $team->isTeamLead($user) ||
            $user->hasPermission('manage teams');
    }

    /**
     * Determine whether the user can change the team lead.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function changeTeamLead(User $user, Team $team): bool
    {
        return $team->isTeamLead($user) ||
            $user->hasPermission('team.changeLead');
    }
}
