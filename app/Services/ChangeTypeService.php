<?php

namespace App\Services;

use App\Models\ChangeType;
use App\Models\TaskHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ChangeTypeService
{
    /**
     * Get all change types with optional filters.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllChangeTypes(array $filters = []): Collection
    {
        $query = ChangeType::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (isset($filters['with_task_histories_count']) && $filters['with_task_histories_count']) {
            $query->withCount(['taskHistories', 'taskHistoriesByName']);

            $changeTypes = $query->get();

            foreach ($changeTypes as $changeType) {
                $changeType->total_task_histories_count =
                    ($changeType->task_histories_count ?? 0) +
                    ($changeType->task_histories_by_name_count ?? 0);
            }

            return $changeTypes;
        }

        return $query->get();
    }

    /**
     * Create a new change type.
     *
     * @param array $attributes
     * @return ChangeType
     */
    public function createChangeType(array $attributes): ChangeType
    {
        return ChangeType::create($attributes);
    }

    /**
     * Get a change type by ID.
     *
     * @param int $id
     * @param bool $withTaskHistoriesCount
     * @return ChangeType|null
     */
    public function getChangeType(int $id, bool $withTaskHistoriesCount = false): ?ChangeType
    {
        $changeType = ChangeType::find($id);

        if ($changeType && $withTaskHistoriesCount) {
            $changeType->loadCount(['taskHistories', 'taskHistoriesByName']);
            $changeType->total_task_histories_count =
                ($changeType->task_histories_count ?? 0) +
                ($changeType->task_histories_by_name_count ?? 0);
        }

        return $changeType;
    }

    /**
     * Find a change type by name.
     *
     * @param string $name
     * @return ChangeType|null
     */
    public function findByName(string $name): ?ChangeType
    {
        return ChangeType::where('name', $name)->first();
    }

    /**
     * Update a change type.
     *
     * @param ChangeType $changeType
     * @param array $attributes
     * @return ChangeType
     */
    public function updateChangeType(ChangeType $changeType, array $attributes): ChangeType
    {
        $oldName = $changeType->name;
        $changeType->update($attributes);

        // If name changed, update task histories to use the new change type name
        if ($oldName !== $changeType->name && isset($attributes['name'])) {
            TaskHistory::where('field_changed', $oldName)
                ->update(['field_changed' => $changeType->name]);
        }

        return $changeType->fresh();
    }

    /**
     * Delete a change type.
     *
     * @param ChangeType $changeType
     * @return bool
     * @throws \Exception If change type is in use
     */
    public function deleteChangeType(ChangeType $changeType): bool
    {
        $usageCount = $this->getUsageCount($changeType);

        if ($usageCount > 0) {
            throw new \Exception('Cannot delete change type that is in use.');
        }

        return (bool) $changeType->delete();
    }

    /**
     * Get the number of task histories using this change type.
     *
     * @param ChangeType $changeType
     * @return int
     */
    public function getUsageCount(ChangeType $changeType): int
    {
        return TaskHistory::where('field_changed', $changeType->name)
            ->orWhere('change_type_id', $changeType->id)
            ->count();
    }

    /**
     * Sync task histories with change types based on field_changed values.
     *
     * @return int Number of records updated
     */
    public function syncTaskHistories(): int
    {
        $changeTypes = ChangeType::all(['id', 'name']);
        $updated = 0;

        foreach ($changeTypes as $changeType) {
            $count = TaskHistory::where('field_changed', $changeType->name)
                ->whereNull('change_type_id')
                ->update(['change_type_id' => $changeType->id]);

            $updated += $count;
        }

        return $updated;
    }

    /**
     * Clear the change type cache.
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        return Cache::forget('change_types_all');
    }
}
