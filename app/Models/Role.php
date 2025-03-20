<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string $description
 * @property int $level
 * @property int $organisation_id
 * @property int $template_id
 * @property string $created_at
 * @property string $updated_at
 * @property-read Organisation $organisation
 * @property-read RoleTemplate $template
 */
class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'level',
        'organisation_id',
        'created_at',
        'updated_at',
        'template_id',
        'is_system',
        'overrides_system',
        'system_role_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
        'organisation_id' => 'integer',
        'template_id' => 'integer',
        'overrides_system' => 'boolean',
        'is_system' => 'boolean'
    ];
    /**
     * Get the organization that owns this role.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the template for this role
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class, 'template_id');
    }

    /**
     * Get the permissions attached to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Get the users assigned to this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withTimestamps();
    }

    /**
     * Sync this role with its template permissions.
     */
    public function syncWithTemplate(): bool
    {
        if ($this->template_id) {
            $template = RoleTemplate::find($this->template_id);
            if ($template) {
                // Sync permissions while preserving existing ones not in template
                $currentPermissions = $this->permissions()->pluck('permissions.id')->toArray();
                $templatePermissions = $template->permissions()->pluck('permissions.id')->toArray();

                // Get union of both sets
                $allPermissions = array_unique(array_merge($currentPermissions, $templatePermissions));

                $this->permissions()->sync($allPermissions);

                // Update role attributes if needed
                if ($this->display_name != $template->display_name ||
                    $this->description != $template->description ||
                    $this->level != $template->level) {

                    $this->update([
                        'display_name' => $template->display_name,
                        'description' => $template->description,
                        'level' => $template->level
                    ]);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Create a role from template for an organization.
     */
    public static function createFromTemplate(RoleTemplate $template, $organisationId)
    {
        $roleName = $template->name;

        // Check if role already exists
        $existingRole = self::where('name', $roleName)
            ->where('organisation_id', $organisationId)
            ->first();

        if ($existingRole) {
            return $existingRole;
        }

        // Create new role
        $role = self::create([
            'name' => $roleName,
            'display_name' => $template->display_name,
            'description' => $template->description,
            'level' => $template->level,
            'organisation_id' => $organisationId,
            'template_id' => $template->id
        ]);

        // Attach all template permissions to this role
        $permissions = $template->permissions()->pluck('permissions.id')->toArray();
        $role->permissions()->attach($permissions);

        return $role;
    }

    /**
     * Check if this role has a specific permission.
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
     * Get all roles available to an organization
     * (custom roles + non-overridden system roles)
     */
    public static function getOrganisationRoles(int $organisationId)
    {
        // Get custom org roles
        $orgRoleNames = self::where('organisation_id', $organisationId)
            ->pluck('name')
            ->toArray();

        // Get system roles that aren't overridden
        $systemRoles = self::where('is_system', true)
            ->whereNotIn('name', $orgRoleNames)
            ->get();

        // Get org-specific roles
        $orgRoles = self::where('organisation_id', $organisationId)->get();

        // Combine collections
        return $systemRoles->concat($orgRoles);
    }

    /**
     * Create an organization-specific version of a system role
     * @throws \Exception
     */
    public static function overrideSystemRole(string $roleName, int $organisationId, int $templateId): self
    {
        // Find system role
        $systemRole = self::where('name', $roleName)
            ->where('is_system', true)
            ->first();

        if (!$systemRole) {
            throw new \Exception("System role '{$roleName}' not found");
        }

        // Create the overriding role
        return self::create([
            'name' => $systemRole->name,
            'display_name' => $systemRole->display_name,
            'description' => $systemRole->description,
            'level' => $systemRole->level,
            'organisation_id' => $organisationId,
            'template_id' => $templateId,
            'is_system' => false,
            'overrides_system' => true,
            'system_role_id' => $systemRole->id
        ]);
    }
}
