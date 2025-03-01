<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $name
 * @property string $value
 * @property string $color
 * @property int $position
 */
class Priority extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'value',
        'color',
        'position',
    ];


    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('position');
        });
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
