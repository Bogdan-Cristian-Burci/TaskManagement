<?php

namespace App\Models;

use App\Enums\ChangeTypeEnum;
use App\Traits\HasAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $organisation_id
 * @property int $team_id
 * @property int|null $responsible_user_id
 * @property string $key
 * @property string|null $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organisation $organisation
 * @property-read Team $team
 * @property-read Collection|Task[] $tasks
 * @property-read Collection|User[] $users
 * @property-read Collection|Board[] $boards
 * @property-read Collection|Tag[] $tags
 * @property-read int|null $tasks_count
 * @property-read int|null $users_count
 * @property-read int|null $boards_count
 */
class Project extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    /**
     * Task handling options for project deletion
     */
    public const TASK_HANDLING = [
        'DELETE' => 'delete_tasks',
        'MOVE' => 'move_tasks',
        'KEEP' => 'keep_tasks',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'organisation_id',
        'team_id',
        'responsible_user_id',
        'key',
        'status',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'organisation_id' => 'integer',
        'team_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            // Auto-generate key if not provided
            if (!$project->key) {
                $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $project->name), 0, 3));
                $project->key = $prefix . '-' . ($project->id ?? Str::random(5));
            }

            // Set default status if not provided
            if (!$project->status) {
                $project->status = 'planning'; // Default status
            }
        });

        static::created(function (Project $project) {
            // Update key with ID after creation if key contains random string
            if (Str::contains($project->key, '-')) {
                $prefix = explode('-', $project->key)[0];
                $project->key = $prefix . '-' . $project->id;
                $project->save();
            }
        });
    }

    /**
     * Get the organisation that owns the project.
     *
     * @return BelongsTo
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Get the team that owns the project.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Get the tasks for the project.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * Get the users associated with the project.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the boards for the project.
     *
     * @return HasMany
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    /**
     * Get the tags for the project.
     *
     * @return HasMany
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function getAllAvailableTags()
    {
        $projectTags = $this->tags;

        $systemTags = Tag::where('is_system', true)
            ->whereNull('project_id')
            ->get();

        return $projectTags->concat($systemTags);
    }

    /**
     * Get the user responsible for this project.
     *
     * @return BelongsTo
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }


    /**
     * Get open tasks for the project.
     *
     * @return HasMany
     */
    public function openTasks(): HasMany
    {
        return $this->tasks()->where('status', '!=', 'completed');
    }

    /**
     * Calculate project progress percentage.
     *
     * @return float
     */
    public function getProgressAttribute(): float
    {
        static $completedStatusId = null;
        if ($completedStatusId === null) {
            $completedStatusId = Status::where('name', 'Completed')->first()->id ?? 0;
        }

        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->tasks()->where('status_id', $completedStatusId)->count();
        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Check if the project is overdue.
     *
     * @return bool
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date && $this->end_date < now() && $this->progress < 100;
    }

    /**
     * Get the default board for this project.
     *
     * @return Board|null
     */
    public function getDefaultBoardAttribute(): ?Board
    {
        return $this->boards()->where('is_default', true)->first();
    }

    /**
     * Scope a query to include projects for a specific team.
     *
     * @param Builder $query
     * @param int $teamId
     * @return Builder
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to include projects for a specific organisation.
     *
     * @param Builder $query
     * @param int $organisationId
     * @return Builder
     */
    public function scopeForOrganisation($query, $organisationId)
    {
        return $query->where('organisation_id', $organisationId);
    }

    /**
     * Scope a query to include projects with a specific status.
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to include active projects.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('status', 'active');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'key', 'description', 'status', 'start_date', 'end_date', 'organisation_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('project');
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        // Add organization context - NOTE: Fixed the spelling to match your model (organisation with an 's')
        if ($this->organisation) {
            $activity->properties = $activity->properties->merge([
                'organization_name' => $this->organisation->name ?? 'Unknown',
            ]);

            // Set change type if you have a ChangeType model
            $activity->change_type_id = ChangeType::where('name', ChangeTypeEnum::PROJECT->value)->value('id');

            // Save the changes
            $activity->save();
        }
    }
}
