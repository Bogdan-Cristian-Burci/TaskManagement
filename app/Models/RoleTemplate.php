<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleTemplate extends Model
{
    protected $fillable = ['name', 'description', 'permissions', 'organisation_id'];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the organization that owns the template
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the roles that use this template
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'template_id');
    }

    /**
     * Scope templates to a specific organization
     */
    public function scopeForOrganisation($query, $organisationId)
    {
        return $query->where('organisation_id', $organisationId);
    }

    /**
     * Check if the template contains a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }
}
