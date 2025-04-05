<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $color
 * @property int $project_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read Collection|Task[] $tasks
 */
class Tag extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'color',
        'project_id', // Changed from projects_id to project_id for consistency
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'project_id' => 'integer',
    ];

    /**
     * Get the project that owns the tag.
     *
     * @return BelongsTo
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the tasks that belong to this tag.
     *
     * @return BelongsToMany
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'tag_task', 'tag_id', 'task_id')
            ->withTimestamps();
    }

    /**
     * Get the hex color code with hash prefix.
     *
     * @return string
     */
    public function getHexColorAttribute(): string
    {
        // If the color already starts with #, return as is
        if (str_starts_with($this->color, '#')) {
            return $this->color;
        }

        // Otherwise, add the # prefix
        return "#{$this->color}";
    }

    /**
     * Scope a query to only include tags for a specific project.
     *
     * @param Builder $query
     * @param int $projectId
     * @return Builder
     */
    public function scopeForProject(Builder $query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope a query to only include tags with a specific color.
     *
     * @param Builder $query
     * @param string $color
     * @return Builder
     */
    public function scopeWithColor($query, $color)
    {
        // Remove # if present for consistent search
        $color = ltrim($color, '#');
        return $query->where('color', 'LIKE', "%{$color}%");
    }
}
