<?php

namespace App\Models;

use App\Enums\ChangeTypeEnum;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;

/**
 * @property integer $id
 * @property string $name
 * @property integer $from_status_id
 * @property integer $to_status_id
 * @property integer|null $board_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property-read Status $fromStatus
 * @property-read Status $toStatus
 * @property-read Board|null $board
 */
class StatusTransition extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    protected $fillable = [
        'name',
        'from_status_id',
        'to_status_id',
        'board_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'from_status_id' => 'integer',
        'to_status_id' => 'integer',
        'board_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the source status for this transition.
     *
     * @return BelongsTo
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    /**
     * Get the target status for this transition.
     *
     * @return BelongsTo
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    /**
     * Get the board for this transition.
     *
     * @return BelongsTo
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Scope a query to transitions for a specific board.
     *
     * @param Builder $query
     * @param int $boardId
     * @return Builder
     */
    public function scopeForBoard($query, int $boardId): Builder
    {
        return $query->where('board_id', $boardId);
    }

    /**
     * Check if this transition is valid for a given task.
     *
     * @param Task $task
     * @return bool
     */
    public function isValidFor(Task $task): bool
    {
        // Check if the board matches (or is null for global transitions)
        if ($this->board_id !== null && $this->board_id !== $task->board_id) {
            return false;
        }

        // Check if task's current status matches the from_status_id
        return $this->from_status_id === $task->status_id;
    }

    /**
     * Set the change type for activity logs.
     *
     * @param Activity $activity
     * @param string $eventName
     * @return void
     */
    protected function tapActivity(Activity $activity, string $eventName): void
    {
        // Set the change type to STATUS
        $activity->change_type_id = ChangeType::where('name', ChangeTypeEnum::STATUS->value)->value('id');

        // If related to a board, add board context
        if ($this->board) {
            $activity->properties = $activity->properties->merge([
                'board_name' => $this->board->name ?? 'Unknown',
                'project_name' => $this->board->project->name ?? 'Unknown',
            ]);
        }

        // Add status context
        if ($this->fromStatus && $this->toStatus) {
            $activity->properties = $activity->properties->merge([
                'from_status' => $this->fromStatus->name ?? 'Unknown',
                'to_status' => $this->toStatus->name ?? 'Unknown',
            ]);
        }

        // Save the changes
        $activity->save();
    }
}
