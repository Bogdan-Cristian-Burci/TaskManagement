<?php

namespace App\Repositories\Interfaces;

use App\Models\Status;
use Illuminate\Database\Eloquent\Collection;

interface StatusRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a status by name.
     *
     * @param string $name
     * @return Status|null
     */
    public function findByName(string $name): ?Status;

    /**
     * Get the default status.
     *
     * @return Status|null
     */
    public function getDefault(): ?Status;

    /**
     * Get all statuses by category.
     *
     * @param string $category
     * @return Collection
     */
    public function getByCategory(string $category): Collection;

    /**
     * Reorder status positions.
     *
     * @param array $ids Ordered array of status IDs
     * @return bool
     */
    public function reorder(array $ids): bool;
}
