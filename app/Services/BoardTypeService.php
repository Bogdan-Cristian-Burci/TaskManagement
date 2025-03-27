<?php

namespace App\Services;

use App\Models\BoardType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BoardTypeService
{
    /**
     * Get board types with optional filtering, sorting, and pagination.
     *
     * @param array $filters
     * @param array $with
     * @param bool $paginate
     * @param int $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getBoardTypes(
        array $filters = [],
        array $with = [],
        bool $paginate = true,
        int $perPage = 15
    ): Collection|LengthAwarePaginator {
        $query = BoardType::query();

        // Apply name filter
        if (isset($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        // Apply sorting
        $sortField = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        // Load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        // Return paginated results or collection
        if ($paginate) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get a board type by ID with optional relationships.
     *
     * @param int $id
     * @param array $with
     * @return BoardType|null
     */
    public function getBoardType(int $id, array $with = []): ?BoardType
    {
        $query = BoardType::where('id', $id);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    /**
     * Create a new board type.
     *
     * @param array $attributes
     * @return BoardType
     */
    public function createBoardType(array $attributes): BoardType
    {
        return BoardType::create($attributes);
    }

    /**
     * Update a board type.
     *
     * @param BoardType $boardType
     * @param array $attributes
     * @return BoardType
     */
    public function updateBoardType(BoardType $boardType, array $attributes): BoardType
    {
        $boardType->update($attributes);
        return $boardType->fresh();
    }

    /**
     * Delete a board type.
     *
     * @param BoardType $boardType
     * @return bool
     * @throws \Exception If board type has associated boards
     */
    public function deleteBoardType(BoardType $boardType): bool
    {
        $boardCount = $boardType->boards()->count();

        if ($boardCount > 0) {
            throw new \Exception("Cannot delete board type that has associated boards ({$boardCount}).");
        }

        return (bool) $boardType->delete();
    }

    /**
     * Get the count of boards associated with a board type.
     *
     * @param BoardType $boardType
     * @return int
     */
    public function getBoardCount(BoardType $boardType): int
    {
        return $boardType->boards()->count();
    }

    /**
     * Get or create a board type with a specific template.
     *
     * @param int $templateId
     * @param string $name
     * @param string|null $description
     * @return BoardType
     */
    public function getOrCreateWithTemplate(
        int $templateId,
        string $name,
        ?string $description = null
    ): BoardType
    {
        return DB::transaction(function () use ($templateId, $name, $description) {
            $boardType = BoardType::where('name', $name)->first();

            if (!$boardType) {
                $boardType = BoardType::create([
                    'name' => $name,
                    'description' => $description,
                    'board_template_id' => $templateId
                ]);
            }

            return $boardType;
        });
    }
}
