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
}
