<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property string $color
 * @property string $icon
 * @property boolean $is_default
 * @property integer $position
 * @property string $category
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property-read Task[] $tasks
 * @property-read StatusTransition[] $toTransitions
 * @property-read StatusTransition[] $fromTransitions
 */
class Status extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'is_default',
        'position',
        'category',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_default' => 'boolean',
        'position' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the tasks for this status.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the transitions where this status is the destination.
     *
     * @return HasMany
     */
    public function toTransitions(): HasMany
    {
        return $this->hasMany(StatusTransition::class, 'to_status_id');
    }

    /**
     * Get the transitions where this status is the source.
     *
     * @return HasMany
     */
    public function fromTransitions(): HasMany
    {
        return $this->hasMany(StatusTransition::class, 'from_status_id');
    }

    /**
     * Get the available target statuses for transition from this status.
     *
     * @param int|null $boardId Optional board ID to filter transitions
     * @return \Illuminate\Support\Collection
     */
    public function getAvailableTransitions(?int $boardId = null)
    {
        $query = $this->fromTransitions();

        if ($boardId !== null) {
            $query->where('board_id', $boardId);
        }

        return $query->with('toStatus')->get()->pluck('toStatus');
    }

    /**
     * Check if this status can transition to another status.
     *
     * @param Status $toStatus The target status
     * @param int|null $boardId Optional board ID to check transitions
     * @return bool
     */
    public function canTransitionTo(Status $toStatus, ?int $boardId = null): bool
    {
        $query = $this->fromTransitions()
            ->where('to_status_id', $toStatus->id);

        if ($boardId !== null) {
            $query->where('board_id', $boardId);
        }

        return $query->exists();
    }
}
