<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Get the owner of the organization.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
     * Get all available roles for this organization
     * (includes system roles not overridden + custom roles)
     */
    public function getAvailableRoles()
    {
        // Get system role names that are overridden
        $overriddenNames = $this->roles()
            ->where('overrides_system', true)
            ->pluck('name')
            ->toArray();

        // Get system roles that aren't overridden
        $systemRoles = Role::whereNull('organisation_id')
            ->whereNotIn('name', $overriddenNames)
            ->get();

        // Get org-specific roles
        $orgRoles = $this->roles;

        // Combine collections
        return $systemRoles->concat($orgRoles);
    }

    /**
     * Create standard roles from system templates for this organization.
     */
    public function createStandardRoles(): array
    {
        $created = [];

        // Get all system templates
        $systemTemplates = RoleTemplate::where('is_system', true)
            ->whereNull('organisation_id')
            ->get();

        foreach ($systemTemplates as $template) {
            // Check if role already exists
            $exists = $this->roles()
                ->where('name', $template->name)
                ->exists();

            if (!$exists) {
                // Create from template
                $role = $template->createRoleInOrganisation($this);
                $created[] = $role->name;

                // If this is admin role and we have an owner, assign them
                if ($template->name === 'admin' && $this->owner_id) {
                    $owner = User::find($this->owner_id);
                    if ($owner) {
                        \DB::table('model_has_roles')->insert([
                            'role_id' => $role->id,
                            'model_id' => $owner->id,
                            'model_type' => User::class,
                            'organisation_id' => $this->id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
        }

        return [
            'created_roles' => $created,
            'organisation_id' => $this->id
        ];
    }

    /**
     * Create a custom role template.
     */
    public function createRoleTemplate(array $data, array $permissionIds = []): RoleTemplate
    {
        $data['organisation_id'] = $this->id;
        $data['is_system'] = false;

        $template = RoleTemplate::create($data);

        if (!empty($permissionIds)) {
            $template->permissions()->attach($permissionIds);
        }

        return $template;
    }

    /**
     * Override a system role with custom template.
     */
    public function overrideSystemRole(string $roleName, int $templateId): ?Role
    {
        // Find system role
        $systemRole = Role::getSystemRole($roleName);

        if (!$systemRole) {
            return null;
        }

        // Create the overriding role
        return Role::create([
            'name' => $systemRole->name,
            'display_name' => $systemRole->display_name,
            'description' => $systemRole->description,
            'level' => $systemRole->level,
            'organisation_id' => $this->id,
            'template_id' => $templateId,
            'overrides_system' => true,
            'system_role_id' => $systemRole->id,
            'guard_name' => 'api'
        ]);
    }
}
