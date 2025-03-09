<?php

namespace App\Models;

use App\Notifications\PasswordResetNotification;
use App\Services\AuthorizationService;
use App\Traits\HasOrganizationPermissions;
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
        HasRoles,
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
     */
    public function hasOrganisationPermission(string $permission, Organisation $organisation): bool
    {
        return App::make(AuthorizationService::class)
            ->hasOrganisationPermission($this, $permission, $organisation);
    }


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
            ->whereIn('name', $roles)
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
            ->where('organisation_id', $organisationId)
            ->get();
    }
}
