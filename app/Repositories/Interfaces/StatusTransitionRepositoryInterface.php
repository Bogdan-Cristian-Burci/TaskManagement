<?php

namespace App\Repositories\Interfaces;

use App\Models\StatusTransition;
use Illuminate\Database\Eloquent\Collection;

interface StatusTransitionRepositoryInterface extends RepositoryInterface
{
    /**
     * Find transitions for a specific board.
     *
     * @param int $boardId
     * @return Collection
     */
    public function findForBoard(int $boardId): Collection;

    /**
     * Find transitions for a specific board template.
     *
     * @param int $boardTemplateId
     * @return Collection
     */
    public function findForBoardTemplate(int $boardTemplateId): Collection;

    /**
     * Find transitions between two specific statuses.
     *
     * @param int $fromStatusId
     * @param int $toStatusId
     * @param int|null $boardId Optional board ID filter
     * @return StatusTransition|null
     */
    public function findBetweenStatuses(int $fromStatusId, int $toStatusId, ?int $boardId = null): ?StatusTransition;

    /**
     * Get all transitions from a specific status.
     *
     * @param int $fromStatusId
     * @param int|null $boardId Optional board ID filter
     * @return Collection
     */
    public function findFromStatus(int $fromStatusId, ?int $boardId = null): Collection;
}
