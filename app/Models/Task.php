<?php

namespace App\Models;

use App\Events\TaskMovedEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property integer $project_id
 * @property integer $board_id
 * @property integer $board_column_id
 * @property integer $status_id
 * @property integer $priority_id
 * @property integer $task_type_id
 * @property integer $responsible_id
 * @property integer $reporter_id
 * @property integer $task_number
 * @property integer $parent_task_id
 * @property float $estimated_hours
 * @property float $spent_hours
 * @property Carbon $start_date
 * @property Carbon $due_date
 * @property integer $position
 * @property Project $project
 * @property Board $board
 * @property BoardColumn $boardColumn
 * @property Task[] $subtasks
 * @property Task $parentTask
 * @property Comment[] $comments
 * @property Attachment[] $attachments
 * @property TaskHistory[] $history
 * @property Status $status
 * @property Priority $priority
 * @property TaskType $taskType
 * @property User $responsible
 * @property User $reporter
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        'name',
        'description',
        'project_id',
        'board_id',
        'board_column_id',
        'status_id',
        'priority_id',
        'task_type_id',
        'responsible_id',
        'reporter_id',
        'task_number',
        'parent_task_id',
        'estimated_hours',
        'spent_hours',
        'start_date',
        'due_date',
        'position'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'estimated_hours' => 'float',
        'spent_hours' => 'float',
        'position' => 'integer',
    ];


    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class,'project_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class,'board_id');
    }

    public function boardColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class); // Note the spelling correction
    }

    public function history(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /// Scope methods with cached status IDs
    public function scopeActive($query)
    {
        static $closedStatusId = null;
        if ($closedStatusId === null) {
            $closedStatusId = Status::where('name', 'Closed')->first()->id;
        }
        return $query->where('status_id', '!=', $closedStatusId);
    }

    public function scopeOverdue($query)
    {
        static $completedStatusId = null;
        static $closedStatusId = null;

        if ($completedStatusId === null) {
            $completedStatusId = Status::where('name', 'Completed')->first()->id;
        }

        if ($closedStatusId === null) {
            $closedStatusId = Status::where('name', 'Closed')->first()->id;
        }

        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status_id', [$completedStatusId, $closedStatusId]);
    }

    public function isOverdue(): bool
    {
        static $completedStatusId = null;
        static $closedStatusId = null;

        if ($completedStatusId === null) {
            $completedStatusId = Status::where('name', 'Completed')->first()->id;
        }

        if ($closedStatusId === null) {
            $closedStatusId = Status::where('name', 'Closed')->first()->id;
        }

        return $this->due_date && $this->due_date < now() &&
            !in_array($this->status_id, [$completedStatusId, $closedStatusId]);
    }

    public function sprints(): BelongsToMany
    {
        return $this->belongsToMany(Sprint::class, 'sprint_task', 'task_id', 'sprint_id');
    }

    public function scopeIncomplete($query)
    {
        static $doneStatusId = null;
        static $canceledStatusId = null;

        if ($doneStatusId === null) {
            $doneStatusId = Status::where('name', 'Done')->first()->id ?? 0;
        }

        if ($canceledStatusId === null) {
            $canceledStatusId = Status::where('name', 'Canceled')->first()->id ?? 0;
        }

        return $query->whereNotIn('status_id', [$doneStatusId, $canceledStatusId]);
    }

    /**
     * Move this task to a new board column, respecting workflow rules.
     *
     * @param BoardColumn $targetColumn The target column to move the task to
     * @param bool $force Whether to bypass workflow rules (for admin use)
     * @return bool Whether the move was successful
     */
    public function moveToColumn(BoardColumn $targetColumn, bool $force = false): bool
    {
        $currentColumn = $this->boardColumn;

        // Skip validation if forced (admin action) or if there are no transition rules
        if (!$force && !empty($currentColumn->allowed_transitions)) {
            $allowedColumnIds = $currentColumn->allowed_transitions;

            if (!in_array($targetColumn->id, $allowedColumnIds)) {
                // Move not allowed by workflow rules
                return false;
            }
        }

        // Check WIP limit before moving
        if ($targetColumn->wip_limit && !$force) {
            $currentCount = $targetColumn->tasks()->count();
            if ($currentCount >= $targetColumn->wip_limit) {
                // WIP limit reached
                return false;
            }
        }

        // Store the old column for event data
        $oldColumn = $this->boardColumn;

        // Update the task's column
        $this->board_column_id = $targetColumn->id;

        // Update status if column maps to a status
        if ($targetColumn->maps_to_status_id) {
            $this->status_id = $targetColumn->maps_to_status_id;
        }

        // Save the changes
        $saved = $this->save();

        // Dispatch task moved event if successful
        if ($saved) {
            event(new TaskMovedEvent($this, $oldColumn, $targetColumn));
        }

        return $saved;
    }

}
