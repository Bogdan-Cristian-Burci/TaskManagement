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
 * @property integer|null $board_template_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property-read Status $fromStatus
 * @property-read Status $toStatus
 * @property-read Board|null $board
 */
class StatusTransition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'from_status_id',
        'to_status_id',
        'board_template_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'from_status_id' => 'integer',
        'to_status_id' => 'integer',
        'board_template_id' => 'integer',
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
    public function boardTemplate(): BelongsTo
    {
        return $this->belongsTo(BoardTemplate::class);
    }

    /**
     * Scope a query to transitions for a specific board.
     *
     * @param Builder $query
     * @param int $boardTemplateId
     * @return Builder
     */
    public function scopeForBoardTemplate(Builder $query, int $boardTemplateId): Builder
    {
        return $query->where('board_template_id', $boardTemplateId);
    }

    /**
     * Check if this transition is valid for a given task.
     *
     * @param Task $task
     * @return bool
     */
    public function isValidFor(Task $task): bool
    {
        // Get the board's template ID
        $boardTemplateId = $task->board?->board_type?->template_id;

        // Check if the board template matches
        if ($this->board_template_id !== null && $this->board_template_id !== $boardTemplateId) {
            return false;
        }

        // Check if task's current status matches the from_status_id
        return $this->from_status_id === $task->status_id;
    }

}
