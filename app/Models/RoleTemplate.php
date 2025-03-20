<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'is_system',
        'organisation_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'level' => 'integer',
    ];

    /**
     * Get the organization this template belongs to (null for system templates).
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Get the roles that use this template.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'template_id');
    }

    /**
     * Get the permissions associated with this template.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'template_has_permissions', 'model_id', 'permission_id')
            ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->join('role_templates', 'roles.template_id', '=', 'role_templates.id')
            ->where('model_has_roles.model_type', User::class)
            ->select('permissions.*');
    }


    /**
     * Check if template has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    /**
     * Scope to get system templates.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true)->whereNull('organisation_id');
    }

    /**
     * Scope to get organization-specific templates.
     */
    public function scopeForOrganisation($query, $organisationId)
    {
        return $query->where('organisation_id', $organisationId);
    }

    /**
     * Get template by name prioritizing org-specific over system.
     */
    public static function getTemplateByName(string $name, ?int $organisationId = null)
    {
        // First try organization-specific template if org ID provided
        if ($organisationId) {
            $template = self::where('name', $name)
                ->where('organisation_id', $organisationId)
                ->first();

            if ($template) {
                return $template;
            }
        }

        // Fall back to system template
        return self::where('name', $name)
            ->where('is_system', true)
            ->whereNull('organisation_id')
            ->first();
    }

    /**
     * Create role in organization from this template.
     */
    public function createRoleInOrganisation(Organisation $organisation, array $attributes = []): Role
    {
        $isSystemOverride = false;
        $systemRoleId = null;

        // Check if this is overriding a system role
        if (!$this->is_system && $this->organisation_id === $organisation->id) {
            $systemTemplate = self::where('name', $this->name)
                ->where('is_system', true)
                ->first();

            if ($systemTemplate) {
                $isSystemOverride = true;
                // Find the system role ID
                $systemRole = Role::where('template_id', $systemTemplate->id)
                    ->whereNull('organisation_id')
                    ->first();

                if ($systemRole) {
                    $systemRoleId = $systemRole->id;
                }
            }
        }

        // Merge our attributes with defaults
        $roleData = array_merge([
            'organisation_id' => $organisation->id,
            'template_id' => $this->id,
            'overrides_system' => $isSystemOverride,
            'system_role_id' => $systemRoleId,
            'guard_name' => 'api',
        ], $attributes);

        return Role::create($roleData);
    }
}
