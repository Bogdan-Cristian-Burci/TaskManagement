<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description'
    ];

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * Get the role templates that have this permission.
     */
    public function roleTemplates(): BelongsToMany
    {
        return $this->belongsToMany(RoleTemplate::class, 'template_permissions', 'permission_id', 'template_id');
    }

    /**
     * Get the users that have this permission directly.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot('organisation_id', 'grant')
            ->withTimestamps();
    }
}
