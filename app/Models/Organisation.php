<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organisation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'unique_id',
        'owner_id',
        'created_by'
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($organisation) {
            if (empty($organisation->slug)) {
                $organisation->slug = Str::slug($organisation->name);
            }

            // Generate unique_id if not provided
            if (empty($organisation->unique_id)) {
                $organisation->unique_id = strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Get the owner of the organization.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user who created the organization.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get users belonging to this organisation.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get projects belonging to this organisation.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get teams belonging to this organisation.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get all roles specific to this organization.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'organisation_id');
    }

    /**
     * Get all role templates specific to this organization.
     */
    public function roleTemplates(): HasMany
    {
        return $this->hasMany(RoleTemplate::class, 'organisation_id');
    }

    /**
     * Check if a user is a member of this organisation.
     *
     * @param int|User $user
     * @return bool
     */
    public function hasMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        return $this->users()
            ->where('users.id', $userId)
            ->exists();
    }

    /**
     * Check if a user is the owner of this organisation.
     *
     * @param int|User $user
     * @return bool
     */
    public function isOwner(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        return $this->owner_id === $userId;
    }

    /**
     * Check if a user is an admin in this organisation.
     *
     * @param int|User $user
     * @return bool
     */
    public function isAdmin(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : (int) $user;

        if ($this->isOwner($userId)) {
            return true;
        }

        return $this->users()
            ->where('users.id', $userId)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Get all available roles for this organization
     * (includes system roles not overridden + custom roles)
     */
    public function getAvailableRoles()
    {
        // First, get template names that are overridden in this organization
        $overriddenTemplateIds = $this->roles()
            ->where('overrides_system', true)
            ->pluck('template_id')
            ->toArray();

        // Get template names from these IDs
        $overriddenTemplateNames = RoleTemplate::whereIn('id', $overriddenTemplateIds)
            ->pluck('name')
            ->toArray();

        // Get system roles that aren't overridden (by template name)
        $systemRoles = Role::whereNull('organisation_id')
            ->whereHas('template', function($query) use ($overriddenTemplateNames) {
                $query->where('is_system', true)
                    ->whereNotIn('name', $overriddenTemplateNames);
            })
            ->get();

        // Get org-specific roles
        $orgRoles = $this->roles;

        // Combine collections
        return $systemRoles->concat($orgRoles);
    }
}
