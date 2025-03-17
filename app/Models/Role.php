<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'level',
        'organisation_id',
        'created_at',
        'updated_at',
        'template_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
        'organisation_id' => 'integer',
        'template_id' => 'integer'
    ];
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Get the template for this role
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class, 'template_id');
    }

    /**
     * Check if the role has permission via its template
     */
    public function hasTemplatePermission(string $permission): bool
    {
        if (!$this->template) {
            return false;
        }

        return $this->template->hasPermission($permission);
    }
}
