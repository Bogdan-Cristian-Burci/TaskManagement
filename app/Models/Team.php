<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'name',
        'organisation_id',
        'team_lead_id',
        'description',
    ];

    protected $casts = [
        'id' => 'integer',
        'organisation_id' => 'integer',
        'team_lead_id' => 'integer',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class,'organisation_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class,'team_user','team_id','user_id');
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function projects(): HasMany // Add this method
    {
        return $this->hasMany(Project::class, 'team_id');
    }
}
