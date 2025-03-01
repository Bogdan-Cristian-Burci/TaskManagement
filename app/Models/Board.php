<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'project_id',
        'board_type_id'
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
        return $this->belongsTo(BoardType::class);
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
}
