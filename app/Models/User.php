<?php

namespace App\Models;

use App\Notifications\PasswordResetNotification;
use App\Services\AuthorizationService;
use App\Traits\HasOrganizationPermissions;
use App\Traits\HasOrganizationRoles;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $email_verified_at
 * @property int|null $organisation_id
 * @property string|null $provider OAuth provider name (e.g. 'github', 'google')
 * @property string|null $provider_id User ID from the OAuth provider
 * @property string|null $avatar User avatar URL
 * @property string|null $phone
 * @property string|null $bio
 * @property string|null $job_title
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection|Organisation[] $organisations
 * @property-read Organisation|null $organisation
 * @property-read Collection|Team[] $teams
 * @property-read Collection|Project[] $projects
 * @property-read Collection|Task[] $tasksResponsibleFor
 * @property-read Collection|Task[] $tasksReported
 * @property-read DatabaseNotificationCollection|DatabaseNotification[] $notifications
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory,
        Notifiable,
        HasApiTokens,
        HasOrganizationRoles,
        HasOrganizationPermissions,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organisation_id',
        'provider',
        'provider_id',
        'avatar',
        'phone',
        'bio',
        'job_title'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'provider_id',
        'provider'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
            'organisation_id' => 'integer',
        ];
    }

    /**
     * Get the organisations that the user belongs to.
     *
     * @return BelongsToMany
     */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the primary organisation that the user belongs to.
     *
     * @return BelongsTo
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Get the teams that the user belongs to.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withTimestamps();
    }

    /**
     * Get the projects that the user belongs to.
     *
     * @return BelongsToMany
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withTimestamps();
    }

    /**
     * Get the tasks that the user is responsible for.
     *
     * @return HasMany
     */
    public function tasksResponsibleFor(): HasMany
    {
        return $this->hasMany(Task::class, 'responsible_id');
    }

    /**
     * Get the tasks that the user has reported.
     *
     * @return HasMany
     */
    public function tasksReported(): HasMany
    {
        return $this->hasMany(Task::class, 'reporter_id');
    }

    /**
     * Get teams where the user is a team lead.
     *
     * @return HasMany
     */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'team_lead_id');
    }

    //TODO fix relationship
    /**
     * Get organisations where the user is an owner.
     *
     * @return BelongsToMany
     */
    public function ownedOrganisations(): BelongsToMany
    {
        return $this->organisations()
            ->wherePivot('role', 'owner');
    }

    //TOD fix relationship
    /**
     * Get organisations where the user is an admin.
     *
     * @return BelongsToMany
     */
    public function adminOrganisations(): BelongsToMany
    {
        return $this->organisations()
            ->wherePivot('role', 'admin');
    }

    /**
     * Check if the user is a member of a given organisation.
     *
     * @param Organisation|int $organisation
     * @return bool
     */
    public function isMemberOf($organisation): bool
    {
        $organisationId = $organisation instanceof Organisation ? $organisation->id : $organisation;
        return $this->organisations()
            ->where('organisations.id', $organisationId)
            ->exists();
    }

    /**
     * Check if the user is a member of a given team.
     *
     * @param Team|int $team
     * @return bool
     */
    public function isInTeam($team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        return $this->teams()
            ->where('teams.id', $teamId)
            ->exists();
    }

    /**
     * Check if the user is the team lead of a given team.
     *
     * @param Team|int $team
     * @return bool
     */
    public function isTeamLeadOf($team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        return $this->ledTeams()
            ->where('id', $teamId)
            ->exists();
    }

    /**
     * Check if the user is assigned to a given project.
     *
     * @param Project|int $project
     * @return bool
     */
    public function isAssignedToProject($project): bool
    {
        $projectId = $project instanceof Project ? $project->id : $project;
        return $this->projects()
            ->where('projects.id', $projectId)
            ->exists();
    }

    /**
     * Get the full name of the user, defaulting to email if name is not set.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return $this->name ?: $this->email;
    }

    /**
     * Get the initials of the user's name.
     *
     * @return string
     */
    public function getInitialsAttribute(): string
    {
        if (!$this->name) {
            return substr($this->email, 0, 2);
        }

        $words = explode(' ', $this->name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }

        return strtoupper(substr($this->name, 0, 2));
    }

    /**
     * Get roles directly from database
     */
    public function getDirectRolesAttribute()
    {
        if (!$this->organisation_id) {
            return [];
        }

        return \DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.organisation_id', $this->organisation_id)
            ->pluck('roles.name')
            ->toArray();
    }

    /**
     * Get the two-factor authentication enabled status.
     *
     * @return bool
     */
    public function getTwoFactorEnabledAttribute(): bool
    {
        return !is_null($this->two_factor_secret);
    }

    /**
     * Check if the user has two-factor authentication enabled.
     *
     * @return bool
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled;
    }

    /**
     * Get recovery codes for two-factor authentication.
     *
     * @return array|null
     */
    public function getRecoveryCodes(): ?array
    {
        if (!$this->two_factor_recovery_codes) {
            return null;
        }

        return json_decode($this->two_factor_recovery_codes, true);
    }

    /**
     * Validate a two-factor authentication recovery code.
     *
     * @param string $code
     * @return bool
     */
    public function validateRecoveryCode(string $code): bool
    {
        $recoveryCodes = $this->getRecoveryCodes();

        if (!$recoveryCodes) {
            return false;
        }

        $index = array_search($code, $recoveryCodes);

        if ($index === false) {
            return false;
        }

        // Remove the used code
        unset($recoveryCodes[$index]);

        // Update recovery codes
        $this->two_factor_recovery_codes = json_encode(array_values($recoveryCodes));
        $this->save();

        return true;
    }

    /**
     * Record a login.
     *
     * @param string $ip The IP address of the login
     * @return void
     */
    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip
        ]);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswordResetNotification($token));
    }

    /**
     * Check if user has permission in organization context
     *
     * @param string $permission
     * @param Organisation|int $organisation
     * @return bool
     */
    public function hasOrganisationPermission(string $permission, $organisation): bool
    {
        // Convert to Organization object if needed
        if (is_numeric($organisation)) {
            $organisationObj = Organisation::find($organisation);
            if (!$organisationObj) {
                return false;
            }
            $organisation = $organisationObj;
        }

        return App::make(AuthorizationService::class)
            ->hasOrganisationPermission($this, $permission, $organisation);
    }

    //TODO fix relationship
    /**
     * Get user's role in specific organization
     */
    public function organisationRole(Organisation $organisation)
    {
        return $this->roles()
            ->where('organisation_id', $organisation->id)
            ->orderByDesc('level')
            ->first();
    }

    /**
     * Check if user has a role within a specific organization
     *
     * @param string|array $roles
     * @param int|null $organisationId
     * @return bool
     */
    public function hasRoleInOrganisation($roles, ?int $organisationId = null): bool
    {
        if (is_null($organisationId)) {
            $organisationId = $this->organisation_id;
        }

        if (!$organisationId) {
            return false;
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        return $this->roles()
            ->where('organisation_id', $organisationId)
            ->whereIn('name', is_array($roles) ? $roles : [$roles])
            ->exists();
    }

    /**
     * Get all roles for a specific organization
     *
     * @param int|null $organisationId
     * @return Collection
     */
    public function getOrganisationRoles(?int $organisationId = null): Collection
    {
        if (is_null($organisationId)) {
            $organisationId = $this->organisation_id;
        }

        if (!$organisationId) {
            return collect();
        }

        return $this->roles()
            ->where('model_has_roles.organisation_id', $organisationId)
            ->get();
    }

    /**
     * Override the morphToMany relationship for roles to include organization_id
     */
    public function roles(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        )->where(function ($query) {
            // Match roles for this user's organization or global roles (no organization)
            $query->where('model_has_roles.organisation_id', $this->organisation_id)
                ->orWhereNull('model_has_roles.organisation_id');
        });
    }

    /**
     * Check permission with organization context
     *
     * @param string $permission
     * @param int|Organisation|null $organization
     * @return bool
     */
    public function canWithOrg(string $permission, $organization = null): bool
    {
        // Use provided organization or try to get from user
        if ($organization === null) {
            $organization = $this->organisation_id;
            if (!$organization) {
                return false;
            }
        }

        // Get organization object if it's an ID
        if (is_numeric($organization)) {
            $orgObj = Organisation::find($organization);
            if (!$orgObj) {
                return false;
            }
            $organization = $orgObj;
        }

        try {
            // Forward to the hasOrganisationPermission method with proper type handling
            return $this->hasOrganisationPermission($permission, $organization);
        } catch (\Exception $e) {
            \Log::error("Permission check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Override can method to support organization context
     *
     * @param string|array $abilities
     * @param mixed|array $arguments
     * @return bool
     */
    public function can($abilities, $arguments = []): bool
    {
        // Extract organization context if present in arguments
        $orgContext = null;
        if (!empty($arguments)) {
            $lastArg = is_array($arguments) ? end($arguments) : $arguments;
            if ($lastArg instanceof Organisation || is_numeric($lastArg)) {
                $orgContext = $lastArg;
            }
        }

        // If we have a special handler for this permission with org context
        if (is_string($abilities) && $orgContext) {
            // Handle organization-specific permissions
            return $this->hasOrganisationPermission($abilities, $orgContext);
        }

        // Fall back to standard Laravel permission check
        return parent::can($abilities, $arguments);
    }

    /**
     * Temporarily switch organization context for permission checks
     *
     * @param int|Organisation $organisation
     * @param callable $callback
     * @return mixed
     */
    public function withOrganisation($organisation, callable $callback): mixed
    {
        $originalOrgId = $this->organisation_id;

        // Set temporary organization context
        $orgId = $organisation instanceof Organisation ? $organisation->id : $organisation;
        $this->organisation_id = $orgId;

        try {
            // Run the callback with the temporary context
            return $callback($this);
        } finally {
            // Restore original organization context
            $this->organisation_id = $originalOrgId;
        }
    }
}
