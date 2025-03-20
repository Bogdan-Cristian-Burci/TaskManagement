<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $unique_id
 * @property string $slug
 * @property string $description
 * @property int $created_by
 * @property string|null $logo
 * @property string|null $address
 * @property string|null $website
 * @property int $owner_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read User $creator
 * @property-read Collection|User[] $users
 * @property-read Collection|Team[] $teams
 * @property-read Collection|Project[] $projects
 */
class Organisation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'unique_id',
        'slug',
        'description',
        'created_by',
        'logo',
        'address',
        'website',
        'owner_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'created_by' => 'integer',
        'owner_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organisation) {
            $organisation->slug = $organisation->slug ?? Str::slug($organisation->name);
        });

        static::updating(function ($organisation) {
            // Only regenerate slug if name changed and slug not explicitly set
            if ($organisation->isDirty('name') && !$organisation->isDirty('slug')) {
                $organisation->slug = Str::slug($organisation->name);
            }
        });
    }
    /**
     * Get the users associated with the organisation.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all admin users in the organisation.
     *
     * @return BelongsToMany
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    /**
     * Get all regular members in the organisation.
     *
     * @return BelongsToMany
     */
    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'member');
    }

    /**
     * Get the teams associated with the organisation.
     *
     * @return HasMany
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the projects associated with the organisation.
     *
     * @return HasMany
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the owner of the organisation.
     *
     * @return BelongsTo
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the creator of the organisation.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine if a user is a member of the organisation.
     *
     * @param User|int $user
     * @return bool
     */
    public function hasMember($user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $this->users()->where('users.id', $userId)->exists();
    }

    /**
     * Determine if a user is an admin of the organisation.
     *
     * @param User|int $user
     * @return bool
     */
    public function isAdmin($user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $this->users()->where('users.id', $userId)
            ->wherePivot('role', 'admin')->exists();
    }

    /**
     * Determine if a user is the owner of the organisation.
     *
     * @param User|int $user
     * @return bool
     */
    public function isOwner($user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $this->owner_id === $userId;
    }

    /**
     * Get the role of a user in the organisation.
     *
     * @param User|int $user
     * @return string|null
     */
    public function getUserRole($user): ?string
    {
        $userId = $user instanceof User ? $user->id : $user;
        $member = $this->users()->where('users.id', $userId)->first();

        if (!$member) {
            return null;
        }

        return $member->pivot->role;
    }

    public function users2(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_user', 'organisation_id', 'user_id');
    }

    /**
     * Get the roles defined for this organization.
     */
    public function roles(): HasMany|Organisation
    {
        return $this->hasMany(Role::class, 'organisation_id');
    }

    /**
     * Create standard roles from templates for this organization.
     */
    public function createStandardRoles(): array
    {
        $createdRoles = [];
        $templates = RoleTemplate::where('is_system', true)->get();

        foreach ($templates as $template) {
            $role = Role::createFromTemplate($template, $this->id);
            $createdRoles[] = $role;
        }

        return $createdRoles;
    }

    /**
     * Create a role from template.
     */
    public function createRoleFromTemplate($templateName)
    {
        $template = RoleTemplate::where('name', $templateName)->first();

        if (!$template) {
            return null;
        }

        return Role::createFromTemplate($template, $this->id);
    }

    /**
     * Create a custom role.
     */
    public function createRole(array $data, array $permissionIds = [])
    {
        $data['organisation_id'] = $this->id;
        $role = Role::create($data);

        if (!empty($permissionIds)) {
            $role->permissions()->attach($permissionIds);
        }

        return $role;
    }

    /**
     * Get members with a specific role.
     */
    public function getMembersWithRole($roleName)
    {
        $role = $this->roles()->where('name', $roleName)->first();

        if (!$role) {
            return collect([]);
        }

        return $role->users;
    }

    /**
     * Add member to this organization with a specific role.
     */
    public function addMemberWithRole(User $user, $roleName): bool
    {
        // Attach user to organization if not already attached
        if (!$user->organisations()->where('organisations.id', $this->id)->exists()) {
            $user->organisations()->attach($this->id);
        }

        // Find role by name
        $role = $this->roles()->where('name', $roleName)->first();

        if (!$role) {
            $roleTemplate = RoleTemplate::where('name', $roleName)->first();
            if ($roleTemplate) {
                $role = Role::createFromTemplate($roleTemplate, $this->id);
            } else {
                return false;
            }
        }

        // Attach role to user
        return $user->attachRole($role, $this->id);
    }

    /**
     * Get all roles accessible by this organization
     * (custom + non-overridden system roles)
     */
    public function getAvailableRoles()
    {
        return Role::getOrganisationRoles($this->id);
    }

    /**
     * Check if this organization has overridden a system role
     */
    public function hasOverriddenRole(string $roleName): bool
    {
        return $this->roles()
            ->where('name', $roleName)
            ->where('overrides_system', true)
            ->exists();
    }

    /**
     * Get effective role (either system or overridden)
     */
    public function getRole(string $roleName)
    {
        // First check for custom override
        $overrideRole = $this->roles()
            ->where('name', $roleName)
            ->first();

        if ($overrideRole) {
            return $overrideRole;
        }

        // Fall back to system role
        return Role::where('name', $roleName)
            ->where('is_system', true)
            ->first();
    }
}
