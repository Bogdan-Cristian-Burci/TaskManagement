<?php

namespace App\Repositories\Interfaces;

use App\Models\ChangeType;
use Illuminate\Database\Eloquent\Collection;

interface ChangeTypeRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a change type by name.
     *
     * @param string $name
     * @return ChangeType|null
     */
    public function findByName(string $name): ?ChangeType;

    /**
     * Find all change types by a partial name match.
     *
     * @param string $name
     * @return Collection
     */
    public function findAllByPartialName(string $name): Collection;

    /**
     * Find all change types with task histories count.
     *
     * @return Collection
     */
    public function findWithTaskHistoriesCount(): Collection;

    /**
     * Clear cache for change types.
     *
     * @return void
     */
    public function clearCache(): void;
}
