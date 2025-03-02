<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property string $color
 * @property string $icon
 * @property boolean $is_default
 * @property integer $position
 * @property string $category
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 */
class Status extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'is_default',
        'position',
        'category',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_default' => 'boolean',
        'position' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the tasks for this status.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
