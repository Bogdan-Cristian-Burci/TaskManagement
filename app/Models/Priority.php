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
 * @property string $value
 * @property string $color
 * @property int $level
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Task[] $tasks
 */
class Priority extends Model
{
    use HasFactory, SoftDeletes, HasAuditTrail;

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'level',
    ];


    protected $casts = [
        'level' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('level');
        });
    }

    /**
     * Get the tasks for this priority.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Check if this is the highest priority.
     *
     * @return bool
     */
    public function isHighest(): bool
    {
        return $this->level === 1;
    }

    /**
     * Check if this is the lowest priority.
     *
     * @return bool
     */
    public function isLowest(): bool
    {
        $lowestLevel = static::max('level');
        return $this->level === $lowestLevel;
    }
}
