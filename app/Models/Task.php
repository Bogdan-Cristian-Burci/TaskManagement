<?php

namespace App\Models;

use App\Events\TaskMovedEvent;
use App\Services\OrganizationContext;
use App\Traits\HasAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

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
 * @property string $task_number
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
    use HasFactory, SoftDeletes, HasAuditTrail;


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


    // Add to booted method:
    protected static function booted(): void
    {
        // Apply organization scope automatically
        static::addGlobalScope('organization', function ($builder) {
            if ($orgId = OrganizationContext::getCurrentOrganizationId()) {
                $builder->inOrganization($orgId);
            }
        });
    }

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

    /**
     * Status ID cache for all scope methods
     */
    protected static $statusIdCache = [];
    
    /**
     * Get status ID from cache or database
     */
    protected static function getStatusId(string $name = null, string $category = null): ?int 
    {
        $cacheKey = $name ? "name:$name" : "category:$category";
        
        if (!isset(self::$statusIdCache[$cacheKey])) {
            $query = Status::query();
            
            if ($name) {
                $query->where('name', $name);
            } elseif ($category) {
                $query->where('category', $category);
            }
            
            $status = $query->first();
            self::$statusIdCache[$cacheKey] = $status ? $status->id : 0;
        }
        
        return self::$statusIdCache[$cacheKey] ?: null;
    }
    
    /**
     * Scope for active tasks
     */
    public function scopeActive($query)
    {
        $closedStatusId = self::getStatusId(name: 'Closed');
        return $query->where('status_id', '!=', $closedStatusId);
    }

    /**
     * Scope for overdue tasks
     */
    public function scopeOverdue($query)
    {
        $completedStatusId = self::getStatusId(name: 'Completed');
        $closedStatusId = self::getStatusId(name: 'Closed');

        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status_id', [$completedStatusId, $closedStatusId]);
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        $completedStatusId = self::getStatusId(category: 'done');
        $closedStatusId = self::getStatusId(category: 'canceled');

        return $this->due_date && $this->due_date < now() &&
            !in_array($this->status_id, [$completedStatusId, $closedStatusId]);
    }

    public function sprints(): BelongsToMany
    {
        return $this->belongsToMany(Sprint::class, 'sprint_task', 'task_id', 'sprint_id');
    }

    /**
     * Scope for incomplete tasks
     */
    public function scopeIncomplete($query)
    {
        $doneStatusId = self::getStatusId(name: 'Done');
        $canceledStatusId = self::getStatusId(name: 'Canceled');

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

        // Skip validation if forced (admin action)
        if (!$force) {
            // Check if this transition is allowed
            if ($currentColumn->maps_to_status_id && $targetColumn->maps_to_status_id) {
                // Check if transition is valid using StatusTransition
                $validTransition = StatusTransition::where('from_status_id', $currentColumn->maps_to_status_id)
                    ->where('to_status_id', $targetColumn->maps_to_status_id)
                    ->where(function($query) use ($currentColumn) {
                        $query->where('board_id', $currentColumn->board_id)
                            ->orWhereNull('board_id'); // Include global transitions
                    })
                    ->exists();

                if (!$validTransition) {
                    return false; // Transition not allowed
                }
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

    public function scopeInOrganization($query, $organizationId = null)
    {
        $orgId = $organizationId ?? OrganizationContext::getCurrentOrganizationId();
        return $query->whereHas('project', function ($q) use ($orgId) {
            $q->where('organisation_id', $orgId);
        });
    }

    // Override the base implementation for custom behavior
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status_id', 'priority_id', 'responsible_id', 'name', 'description', 'due_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('task');
    }

    // The package will automatically call this method after logging activity
    public function tapActivity(Activity $activity, string $eventName): void
    {
        // Link with your existing ChangeType model
        if ($activity->properties->has('attributes')) {
            $attributes = $activity->properties->get('attributes');
            $this->setChangeType($activity, $attributes);

            $activity->save();
        }
    }

    // Keep your existing setChangeType method
    protected function setChangeType(Activity $activity, array $attributes): void
    {
        // Get attribute mapping from config
        $attributeMapping = config('change_types.attribute_mapping');

        foreach ($attributeMapping as $attribute => $changeTypeName) {
            if (array_key_exists($attribute, $attributes)) {
                $activity->change_type_id = ChangeType::where('name', $changeTypeName)->value('id');
                break;
            }
        }
    }
}
