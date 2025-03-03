<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $board_id
 * @property string|null $goal
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Board $board
 * @property-read Collection|Task[] $tasks
 * @property-read int|null $tasks_count
 * @property-read float $progress
 * @property-read bool $is_active
 * @property-read bool $is_completed
 * @property-read bool $is_overdue
 */
class Sprint extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'board_id',
        'goal',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'board_id' => 'integer',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'planning',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (Sprint $sprint) {
            // Default status to planning if not specified
            if (!$sprint->status) {
                $sprint->status = 'planning';
            }
        });

        static::saving(function (Sprint $sprint) {
            // Ensure start_date is before end_date
            if ($sprint->start_date && $sprint->end_date && $sprint->start_date->isAfter($sprint->end_date)) {
                // Swap dates or throw an exception
                throw new \InvalidArgumentException('Sprint end date must be after start date');
            }
        });
    }

    /**
     * Get the board that owns the sprint.
     *
     * @return BelongsTo
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Get the project through the board relationship.
     *
     * @return BelongsTo
     */
    public function project()
    {
        return $this->board->project();
    }

    /**
     * Get the tasks for the sprint.
     *
     * @return BelongsToMany
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'sprint_task')
            ->withTimestamps();
    }

    /**
     * Get the completed tasks for the sprint.
     *
     * @return BelongsToMany
     */
    public function completedTasks(): BelongsToMany
    {
        return $this->tasks()->where('status', 'completed');
    }

    /**
     * Get the tasks in progress for the sprint.
     *
     * @return BelongsToMany
     */
    public function tasksInProgress(): BelongsToMany
    {
        return $this->tasks()->where('status', 'in_progress');
    }

    /**
     * Calculate the percentage of completed tasks.
     *
     * @return float
     */
    public function getProgressAttribute(): float
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->completedTasks()->count();
        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Determine if the sprint is active.
     *
     * @return bool
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' &&
            $this->start_date <= Carbon::today() &&
            $this->end_date >= Carbon::today();
    }

    /**
     * Determine if the sprint is completed.
     *
     * @return bool
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Determine if the sprint is overdue.
     *
     * @return bool
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'completed' &&
            $this->end_date < Carbon::today();
    }

    /**
     * Get the number of days remaining in the sprint.
     *
     * @return int|null
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        $today = Carbon::today();
        if ($today > $this->end_date) {
            return 0;
        }

        return $today->diffInDays($this->end_date);
    }

    /**
     * Get the total duration of the sprint in days.
     *
     * @return int|null
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }

        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Find active sprints.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', Carbon::today())
            ->where('end_date', '>=', Carbon::today());
    }

    /**
     * Find completed sprints.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Find sprints in planning.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePlanning($query)
    {
        return $query->where('status', 'planning');
    }

    /**
     * Find overdue sprints (past end date but not completed).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'completed')
            ->where('end_date', '<', Carbon::today());
    }

    /**
     * Find sprints for a specific board.
     *
     * @param Builder $query
     * @param int $boardId
     * @return Builder
     */
    public function scopeForBoard($query, $boardId)
    {
        return $query->where('board_id', $boardId);
    }

    /**
     * Start the sprint.
     *
     * @return bool
     */
    public function start(): bool
    {
        if ($this->status !== 'planning') {
            return false;
        }

        $this->status = 'active';
        $this->start_date = Carbon::today();
        return $this->save();
    }

    /**
     * Complete the sprint.
     *
     * @return bool
     */
    public function complete(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $this->status = 'completed';
        return $this->save();
    }

    public function tasks2(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'sprint_task', 'sprint_id', 'task_id');
    }
}
