<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TeamService
{
    /**
     * Create a new team.
     *
     * @param int $organisationId
     * @param string $name
     * @param string|null $description
     * @param User $teamLead
     * @return Team
     * @throws \Throwable
     */
    public function createTeam(
        int $organisationId,
        string $name,
        ?string $description = null,
        User $teamLead
    ): Team
    {
        return DB::transaction(function () use ($organisationId, $name, $description, $teamLead) {
            $team = Team::create([
                'name' => $name,
                'description' => $description,
                'organisation_id' => $organisationId
            ]);

            // Add the user as team lead using config for role value
            $leadRole = Config::get('roles.organization.team_leader');
            $team->users()->attach($teamLead->id, ['role' => $leadRole]);

            return $team;
        });
    }

    /**
     * Get or create a team by name.
     *
     * @param int $organisationId
     * @param string $name
     * @param User $teamLead
     * @return Team
     */
    public function getOrCreateTeam(int $organisationId, string $name, User $teamLead): Team
    {
        $team = Team::where('name', $name)
            ->where('organisation_id', $organisationId)
            ->first();

        if (!$team) {
            return $this->createTeam($organisationId, $name, null, $teamLead);
        }

        return $team;
    }

    /**
     * Update team member role.
     *
     * @param Team $team
     * @param User $user
     * @param string $role Role from config
     * @return bool
     * @throws InvalidArgumentException If role is invalid
     */
    public function updateMemberRole(Team $team, User $user, string $role): bool
    {
        // Validate role against config
        $validRoles = array_values(Config::get('roles.team'));

        if (!in_array($role, $validRoles)) {
            throw new InvalidArgumentException(
                "Invalid team role. Must be one of: " . implode(', ', $validRoles)
            );
        }

        return (bool) $team->users()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Add member to team.
     *
     * @param Team $team
     * @param User $user
     * @param string|null $role Role from config (defaults to member)
     * @return bool
     */
    public function addMember(Team $team, User $user, ?string $role = null): bool
    {
        // Default to member role if not specified
        if ($role === null) {
            $role = Config::get('roles.team.member');
        }

        // Validate role against config
        $validRoles = array_values(Config::get('roles.team'));
        if (!in_array($role, $validRoles)) {
            throw new InvalidArgumentException(
                "Invalid team role. Must be one of: " . implode(', ', $validRoles)
            );
        }

        // Check if user is already a member
        if ($team->users()->where('users.id', $user->id)->exists()) {
            return $this->updateMemberRole($team, $user, $role);
        }

        $team->users()->attach($user->id, ['role' => $role]);
        return true;
    }
}
