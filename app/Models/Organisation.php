<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int id
 * @property string name
 * @property string description
 * @property int created_by
 * @property string logo
 * @property string address
 * @property string website
 * @property int owner_id
 */
class Organisation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'logo',
        'address',
        'website',
        'owner_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'created_by' => 'integer',
        'owner_id' => 'integer',
    ];

    /**
     * Get the users associated with the organisation.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the teams associated with the organisation.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the projects associated with the organisation.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the owner of the organisation.
     */
    public function owner():BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the creator of the organisation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
