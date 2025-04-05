<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read TaskHistory[] $taskHistories
 */
class ChangeType extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the task changes with this change type.
     * The relationship is based on the field_changed column in TaskHistory.
     *
     * @return HasMany
     */
    public function taskHistories(): HasMany
    {
        return $this->hasMany(TaskHistory::class, 'field_changed', 'name');
    }

    /**
     * Get the task history entries with this change type name.
     * This is for backward compatibility with existing data.
     *
     * @return HasMany
     */
    public function taskHistoriesByName(): HasMany
    {
        return $this->hasMany(TaskHistory::class, 'field_changed', 'name');
    }

    /**
     * Get all task history entries related to this change type
     * (either by ID or by name field)
     *
     * @return HasMany
     */
    public function allTaskHistories()
    {
        return $this->taskHistories()->orWhere('field_changed', $this->name);
    }
}
