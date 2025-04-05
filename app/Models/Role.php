<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;

class Role extends Model
{
    use HasFactory, HasAuditTrail;

    protected $fillable = [
        'organisation_id',
        'template_id',
        'overrides_system',
        'system_role_id'
    ];

    protected $casts = [
        'overrides_system' => 'boolean'
    ];

    /**
     * Get the organization this role belongs to.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Get the template this role uses.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class, 'template_id');
    }

    /**
     * Get the system role this role overrides.
     */
    public function systemRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'system_role_id');
    }

    /**
     * Get the users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id')
            ->where('model_type', User::class)
            ->withPivot('organisation_id')
            ->withTimestamps();
    }

    /**
     * Get name through template relationship
     */
    public function getName()
    {
        return $this->template ? $this->template->name : null;
    }

    /**
     * Get display name through template relationship
     */
    public function getDisplayName()
    {
        return $this->template ? $this->template->display_name : null;
    }

    /**
     * Get description through template relationship
     */
    public function getDescription()
    {
        return $this->template ? $this->template->description : null;
    }

    /**
     * Get level through template relationship
     */
    public function getLevel()
    {
        return $this->template ? $this->template->level : 0;
    }

    /**
     * Get all permissions available through this role's template.
     */
    public function getPermissions()
    {
        return $this->template ? $this->template->permissions : collect([]);
    }

    /**
     * Check if role has a specific permission through its template.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->template ? $this->template->hasPermission($permission) : false;
    }

    /**
     * Get system role by template name.
     */
    public static function getSystemRole(string $templateName): Role
    {
        return self::whereNull('organisation_id')
            ->whereHas('template', function($query) use ($templateName) {
                $query->where('name', $templateName)
                    ->where('is_system', true);
            })
            ->first();
    }

    /**
     * Get effective role for an organization (custom override or system).
     */
    public static function getEffectiveRole(string $templateName, int $organisationId): Role
    {
        // First check for org-specific override
        $role = self::where('organisation_id', $organisationId)
            ->whereHas('template', function($query) use ($templateName) {
                $query->where('name', $templateName);
            })
            ->first();

        if ($role) {
            return $role;
        }

        // Fall back to system role
        return self::getSystemRole($templateName);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'is_system_role'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('role');
    }
}
