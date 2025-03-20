<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'level',
        'organisation_id',
        'is_system'
    ];

    /**
     * Get the permissions associated with this template.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'template_permissions', 'template_id', 'permission_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the roles based on this template.
     */
    public function roles(): HasMany|RoleTemplate
    {
        return $this->hasMany(Role::class, 'template_id');
    }

    /**
     * Check if this template has a specific permission.
     */
    public function hasPermission($permission): bool
    {
        if (is_string($permission)) {
            return $this->permissions()->where('permissions.name', $permission)->exists();
        }

        if (is_int($permission)) {
            return $this->permissions()->where('permissions.id', $permission)->exists();
        }

        if ($permission instanceof Permission) {
            return $this->permissions()->where('permissions.id', $permission->id)->exists();
        }

        return false;
    }

    /**
     * Create org roles from this template for all organizations or a specific one.
     */
    public function createOrganisationRoles($organisationId = null): array
    {
        $roles = [];

        if ($organisationId) {
            // Create for specific organization
            $roles[] = Role::createFromTemplate($this, $organisationId);
        } else {
            // Create for all organizations
            $organisations = Organisation::all();
            foreach ($organisations as $org) {
                $roles[] = Role::createFromTemplate($this, $org->id);
            }
        }

        return $roles;
    }

    public function isSystemTemplate(): bool
    {
        return $this->is_system === true;
    }
}
