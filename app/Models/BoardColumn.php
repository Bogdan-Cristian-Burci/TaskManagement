<?php

namespace App\Models;

use Carbon\Carbon;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Board $board
 * @property Task[] $tasks
 */
class BoardColumn extends Model
{
    protected $fillable = [
        'name',
        'board_id',
        'position',
        'color',
        'wip_limit',
    ];

    protected $casts = [
        'position' => 'integer',
        'wip_limit' => 'integer',
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
}
