<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property string $icon
 * @property string $color
 * @property Task[] $tasks
 * @property integer $organisation_id
 * @property boolean $is_system
 */
class TaskType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'organisation_id',
        'is_system',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deleted_at' => 'datetime',
        'is_system' => 'boolean',
    ];

    /**
     * Get the tasks for the task type
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }


    /**
     * Get the organisation that owns the task type
     *
     * @return BelongsTo
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Scope a query to only include active task types.
     * Note: This is redundant with SoftDeletes trait, but kept for backward compatibility.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope a query to include only system task types and those belonging to a specific organisation.
     *
     * @param Builder $query
     * @param int|null $organisationId
     * @return Builder
     */
    public function scopeAvailableToOrganisation($query, ?int $organisationId)
    {
        return $query->where(function($q) use ($organisationId) {
            $q->where('is_system', true)
                ->orWhere('organisation_id', $organisationId);
        });
    }
}
