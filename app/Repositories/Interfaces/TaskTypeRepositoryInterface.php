<?php

namespace App\Repositories\Interfaces;

use App\Models\TaskType;
use Illuminate\Database\Eloquent\Collection;

interface TaskTypeRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a task type by name.
     *
     * @param string $name
     * @return TaskType|null
     */
    public function findByName(string $name): ?TaskType;

    /**
     * Get all task types with their task counts.
     *
     * @return Collection
     */
    public function getWithTaskCount(): Collection;

    /**
     * Get task types available to an organisation (system types + org-specific types).
     *
     * @param int|null $organisationId
     * @param array $columns
     * @return Collection
     */
    public function getAvailableToOrganisation(?int $organisationId, array $columns = ['*']): Collection;

    /**
     * Get system task types only.
     *
     * @param array $columns
     * @return Collection
     */
    public function getSystemTaskTypes(array $columns = ['*']): Collection;
}
