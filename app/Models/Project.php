<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer $id
 * @property string $name
 * @property string $description
 * @property integer $organisation_id
 * @property integer $team_id
 * @property string $key
 * @property Organisation $organisation
 * @property Team $team
 * @property Task[] $tasks
 * @property User[] $users
 * @property Board[] $boards
 * @property Tag[] $tags
 */
class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'organisation_id',
        'team_id',
        'key',
    ];

    protected $casts = [
        'id' => 'integer',
        'organisation_id' => 'integer',
        'team_id' => 'integer',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class,'team_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class,'project_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id')
            ->withTimestamps()
            ->withPivot('role');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
