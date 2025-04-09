<?php

namespace App\Policies;

use App\Enums\SprintStatusEnum;
use App\Models\Board;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class SprintPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any sprints.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true; // Any authenticated user can view sprints list
    }

    /**
     * Determine whether the user can view the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function view(User $user, Sprint $sprint): Response|bool
    {
        // User can view sprint if they can view the related board
        $board = $sprint->board;
        return $user->hasPermission('project.view', $board->getOrganisationIdAttribute());
    }

    /**
     * Determine whether the user can create sprints.
     *
     * @param User $user
     * @param int|null $boardId
     * @return Response|bool
     */
    public function create(User $user, ?int $boardId = null): Response|bool
    {
        // User needs general permission or board-specific permission
        if ($user->hasPermission('manage-projects')) {
            return true;
        }

        if ($boardId) {
            $board = \App\Models\Board::find($boardId);
            if ($board) {
                // Check if user is a project manager or team lead
                $project = $board->project;

                // Project manager can create sprints
                $isProjectManager = $project->users()
                    ->where('users.id', $user->id)
                    ->wherePivot('role', 'manager')
                    ->exists();

                if ($isProjectManager) {
                    return true;
                }

                // Team lead can create sprints
                $isTeamLead = $user->teams()
                    ->where('teams.id', $project->team_id)
                    ->wherePivot('role', 'lead')
                    ->exists();

                if ($isTeamLead) {
                    return true;
                }
            }
        }

        return Response::deny('You do not have permission to create sprints for this board.');
    }

    /**
     * Determine whether the user can update the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function update(User $user, Sprint $sprint): Response|bool
    {
        // User with general permission can update any sprint
        if ($user->hasPermission('manage-projects')) {
            return true;
        }

        $board = $sprint->board;
        $project = $board->project;

        // Project manager can update sprints
        $isProjectManager = $project->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'manager')
            ->exists();

        if ($isProjectManager) {
            return true;
        }

        // Team lead can update sprints
        $isTeamLead = $user->teams()
            ->where('teams.id', $project->team_id)
            ->wherePivot('role', 'lead')
            ->exists();

        if ($isTeamLead) {
            return true;
        }

        return Response::deny('You do not have permission to update this sprint.');
    }

    /**
     * Determine whether the user can delete the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function delete(User $user, Sprint $sprint): Response|bool
    {
        // Check if sprint has tasks
        if ($sprint->tasks()->count() > 0) {
            return Response::deny('Cannot delete sprint with associated tasks. Please remove tasks first.');
        }

        // Use same permissions as update
        return $this->update($user, $sprint);
    }

    /**
     * Determine whether the user can restore the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function restore(User $user, Sprint $sprint): Response|bool
    {
        return $this->update($user, $sprint);
    }

    /**
     * Determine whether the user can permanently delete the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function forceDelete(User $user, Sprint $sprint): Response|bool
    {
        return $user->hasPermission('manage-projects');
    }

    /**
     * Determine whether the user can add tasks to the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function manageTasks(User $user, Sprint $sprint): Response|bool
    {
        return $this->update($user, $sprint);
    }

    /**
     * Determine whether the user can start the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function start(User $user, Sprint $sprint): Response|bool
    {
        // Can only start sprints in planning status
        if ($sprint->status !== SprintStatusEnum::PLANNING) {
            return Response::deny('Sprint can only be started from planning status.');
        }

        $organizationId = $sprint->organisation->id;

        if (!$user->hasPermission('project.view', $organizationId)) {
            return Response::deny('You do not have permission to start sprints in this organization.');
        }

        return $this->update($user, $sprint);
    }

    /**
     * Determine whether the user can complete the sprint.
     *
     * @param User $user
     * @param Sprint $sprint
     * @return Response|bool
     */
    public function complete(User $user, Sprint $sprint): Response|bool
    {
        // Can only complete active sprints
        if ($sprint->status !== SprintStatusEnum::ACTIVE) {
            return Response::deny('Only active sprints can be completed.');
        }

        return $this->update($user, $sprint);
    }

    public function viewBoardSprints(User $user, Board $board): Response|bool
    {
        \Log::info('Checking viewBoardSprints permission for user from policy: ' . $user->id . ' on board: ' . $board->id);
        return $user->hasPermission('project.view', $board->project->organisation_id);
    }
}
