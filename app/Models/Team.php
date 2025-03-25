<?php

namespace App\Models;

use App\Scopes\OrganizationScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property int $organisation_id
 * @property int $team_lead_id
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organisation $organisation
 * @property-read User $teamLead
 * @property-read Collection|User[] $users
 * @property-read Collection|Project[] $projects
 */
class Team extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'organisation_id',
        'team_lead_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'organisation_id' => 'integer',
        'team_lead_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    /**
     * Get the organisation that the team belongs to.
     *
     * @return BelongsTo
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get a query builder without the organization scope applied.
     *
     * @return Team
     */
    public static function allOrganizations(): Team
    {
        return static::withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * Get the users that belong to the team.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * Get the team leader.
     *
     * @return BelongsTo
     */
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    /**
     * Get the projects assigned to this team.
     *
     * @return HasMany
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the tasks assigned to this team.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Check if a user is a member of this team.
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
     * Check if a user is the team lead.
     *
     * @param User|int $user
     * @return bool
     */
    public function isTeamLead($user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $this->team_lead_id === $userId;
    }

    /**
     * Get all projects and their tasks associated with this team.
     *
     * @return array
     */
    public function getProjectsWithTasks(): array
    {
        return $this->projects()
            ->with('tasks')
            ->get()
            ->toArray();
    }
}
