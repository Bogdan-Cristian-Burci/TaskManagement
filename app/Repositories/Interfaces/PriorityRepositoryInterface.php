<?php

namespace App\Repositories\Interfaces;

use App\Models\Priority;
use Illuminate\Database\Eloquent\Collection;

interface PriorityRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a priority by level.
     *
     * @param int $level
     * @return Priority|null
     */
    public function findByLevel(int $level): ?Priority;

    /**
     * Reorder priorities.
     *
     * @param array $priorityIds Array of priority IDs in the desired order
     * @return bool
     */
    public function reorder(array $priorityIds): bool;
}
