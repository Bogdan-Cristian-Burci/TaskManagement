<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property integer $id
 * @property string $name
 * @property integer $board_id
 * @property integer $position
 * @property string $color
 * @property integer $wip_limit
 * @property integer|null $maps_to_status_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Board $board
 * @property Task[] $tasks
 */
class BoardColumn extends Model
{

    use HasAuditTrail;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'board_id',
        'position',
        'color',
        'wip_limit',
        'maps_to_status_id',
    ];

    protected $casts = [
        'position' => 'integer',
        'wip_limit' => 'integer',
        'maps_to_status_id' => 'integer',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class,'board_column_id');
    }

    /**
     * Check if the column has reached its WIP limit
     */
    public function isAtWipLimit(): bool
    {
        if (!$this->wip_limit) {
            return false;
        }

        return $this->tasks()->count() >= $this->wip_limit;
    }

    /**
     * Get the status that this column maps to
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'maps_to_status_id');
    }

    /**
     * Get the columns this column can transition tasks to.
     * Uses StatusTransition model to determine valid transitions based on template.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllowedTransitionColumns(): \Illuminate\Support\Collection
    {
        if (!$this->maps_to_status_id || !$this->board_id) {
            return collect();
        }

        // Get the board and its template
        $board = $this->board;
        $templateId = $board->board_type->template_id ?? null;

        if (!$templateId) {
            return collect();
        }

        // Get status transitions for this column's status based on template
        $statusTransitions = StatusTransition::where('from_status_id', $this->maps_to_status_id)
            ->where('board_template_id', $templateId)
            ->get();

        // Map to columns in this board with matching status IDs
        $toStatusIds = $statusTransitions->pluck('to_status_id')->unique()->toArray();

        return BoardColumn::where('board_id', $this->board_id)
            ->whereIn('maps_to_status_id', $toStatusIds)
            ->get();
    }
}
