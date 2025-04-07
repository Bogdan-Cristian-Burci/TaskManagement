<?php

namespace App\Models;

use App\Models\Scopes\BoardOrganizationScope;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

/**
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property integer $project_id
 * @property integer $board_type_id
 * @property Project $project
 * @property BoardType $boardType
 * @property BoardColumn[] $columns
 * @property Task[] $tasks
 */
class Board extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'project_id',
        'board_type_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'project_id' => 'integer',
        'board_type_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new BoardOrganizationScope());
    }

    /**
     * Get all boards across organizations (admin function).
     *
     * @return Board
     */
    public static function allOrganizations(): Board
    {
        return static::withoutGlobalScope(BoardOrganizationScope::class);
    }

    /**
     * Get the organization ID for this board through its project relationship.
     *
     * @return int|null
     */
    public function getOrganisationIdAttribute(): ?int
    {
        return Cache::remember(
            "board_{$this->id}_organisation",
            3600, // 1 hour
            fn() => $this->project->organisation_id ?? null
        );
    }

    /**
     * Get the project that owns the board.
     *
     * @return BelongsTo
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the board type that owns the board.
     *
     * @return BelongsTo
     */
    public function boardType(): BelongsTo
    {
        return $this->belongsTo(BoardType::class,'board_type_id');
    }

    /**
     * Define a relationship for compatibility with older code that uses 'board_type'
     * This is an alias for the boardType() relationship
     *
     * @return BelongsTo
     */
    public function board_type(): BelongsTo
    {
        return $this->boardType();
    }

    /**
     * Get the columns for the board.
     *
     * @return HasMany
     */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    /**
     * Get the tasks for the board.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'board_id');
    }

    /**
     * Get the sprints for the board.
     *
     * @return HasMany
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * Get the team associated with the board through the project.
     *
     * @return HasOneThrough
     */
    public function team(): HasOneThrough
    {
        return $this->hasOneThrough(
            Team::class,
            Project::class,
            'id', // Foreign key on projects table
            'id', // Foreign key on teams table
            'project_id', // Local key on boards table
            'team_id' // Local key on projects table
        );
    }
    /**
     * Get the active sprint for the board.
     *
     * @return HasOne
     */
    public function activeSprint() : HasOne
    {
        return $this->hasOne(Sprint::class)->where('status', 'active');
    }

    /**
     * Duplicate this board with its columns.
     *
     * @param string|null $newName
     * @return Board
     */
    public function duplicate(?string $newName = null): Board
    {
        $newBoard = $this->replicate(['id']);
        $newBoard->name = $newName ?? $this->name . ' (Copy)';
        $newBoard->save();

        // Clone columns
        foreach ($this->columns as $column) {
            $newColumn = $column->replicate(['id', 'board_id']);
            $newColumn->board_id = $newBoard->id;
            $newColumn->save();
        }

        return $newBoard;
    }

    /**
     * Get the count of tasks on this board.
     *
     * @return int
     */
    public function getTasksCountAttribute(): int
    {
        return $this->tasks()->count();
    }

    /**
     * Get the count of completed tasks on this board.
     *
     * @return int
     */
    public function getCompletedTasksCountAttribute(): int
    {
        $completedStatusId = Status::where('name', 'Completed')->first()->id ?? null;
        return $completedStatusId ? $this->tasks()->where('status_id', $completedStatusId)->count() : 0;
    }

    /**
     * Get the completion percentage of tasks on this board.
     *
     * @return float
     */
    public function getCompletionPercentageAttribute(): float
    {
        $totalTasks = $this->tasks_count;
        if ($totalTasks === 0) {
            return 0;
        }

        return round(($this->completed_tasks_count / $totalTasks) * 100, 2);
    }
    /**
     * Scope a query to only include boards of a given project.
     *
     * @param Builder $query
     * @param int $projectId
     * @return Builder
     */
    public function scopeByProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }
    /**
     * Scope a query to only include boards of a given type.
     *
     * @param Builder $query
     * @param int $boardTypeId
     * @return Builder
     */
    public function scopeByType(Builder $query, int $boardTypeId): Builder
    {
        return $query->where('board_type_id', $boardTypeId);
    }

    /**
     * Scope a query to include boards with activity in the last X days.
     *
     * @param Builder $query
     * @param int $days
     * @return Builder
     */
    public function scopeWithRecentActivity(Builder $query, int $days = 7): Builder
    {
        $date = now()->subDays($days);

        return $query->whereHas('tasks', function ($query) use ($date) {
            $query->where('updated_at', '>=', $date);
        });
    }

    /**
     * Scope a query to include boards that have active sprints.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithActiveSprints(Builder $query): Builder
    {
        return $query->whereHas('sprints', function ($query) {
            $query->where('status', 'active');
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'project_id', 'board_type_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('board');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($this->project) {
            $activity->properties = $activity->properties->merge([
                'project_name' => $this->project->name ?? 'Unknown',
                'project_code' => $this->project->key ?? 'Unknown',
                'organization_id' => $this->project->organisation_id ?? null
            ]);

            // Save the changes
            $activity->save();
        }
    }
}
