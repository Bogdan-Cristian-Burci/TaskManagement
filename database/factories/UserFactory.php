<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'avatar' => fake()->imageUrl(100, 100, 'people'),
            'phone' => fake()->phoneNumber(),
            'bio' => fake()->paragraph(),
            'job_title' => fake()->jobTitle(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            // Assign the default role if none assigned yet
            if ($user->roles()->count() === 0 && $user->organisation_id) {
                $user->assignRole(['user', ['organisation_id' => $user->organisation_id]]);
            } elseif ($user->roles()->count() === 0) {
                $user->assignRole('user');
            }
        });
    }

    /**
     * Indicate that the user is an admin.
     *
     * @return static
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            return [];
        })->afterCreating(function (User $user) {
            if ($user->organisation_id) {
                $user->assignRole(['admin', ['organisation_id' => $user->organisation_id]]);
            } else {
                $user->assignRole('admin');
            }
        });
    }

    /**
     * Indicate that the user is a super admin.
     *
     * @return static
     */
    public function superAdmin(): static
    {
        return $this->state(function (array $attributes) {
            return [];
        })->afterCreating(function (User $user) {
            $user->assignRole('super-admin');
        });
    }

    /**
     * Indicate that the user belongs to an organisation.
     *
     * @param Organisation|int|null $organisation
     * @param string $role
     * @return static
     */
    public function withOrganisation($organisation = null, string $role = 'member'): static
    {
        return $this->afterCreating(function (User $user) use ($organisation, $role) {
            if (!$organisation) {
                $organisation = Organisation::factory()->create();
            }

            $organisationId = $organisation instanceof Organisation ? $organisation->id : $organisation;

            // Attach user to the organisation with the specified role
            $user->organisations()->syncWithoutDetaching([$organisationId => ['role' => $role]]);

            // Set as primary organisation
            $user->update(['organisation_id' => $organisationId]);
        });
    }

    /**
     * Indicate that the user belongs to multiple organisations.
     *
     * @param int $count
     * @param string $role
     * @return static
     */
    public function withOrganisations(int $count = 2, string $role = 'member'): static
    {
        return $this->afterCreating(function (User $user) use ($count, $role) {
            $organisations = Organisation::factory()->count($count)->create();

            // Prepare the pivot data for sync
            $organisationsWithRole = $organisations->mapWithKeys(function ($org) use ($role) {
                return [$org->id => ['role' => $role]];
            })->toArray();

            // Attach user to the organisations
            $user->organisations()->syncWithoutDetaching($organisationsWithRole);

            // Set the first one as primary organisation
            $user->update(['organisation_id' => $organisations->first()->id]);
        });
    }

    /**
     * Indicate that the user is an organisation owner.
     *
     * @param Organisation|int|null $organisation
     * @return static
     */
    public function organisationOwner($organisation = null): static
    {
        return $this->withOrganisation($organisation, 'owner');
    }

    /**
     * Indicate that the user is an organisation admin.
     *
     * @param Organisation|int|null $organisation
     * @return static
     */
    public function organisationAdmin($organisation = null): static
    {
        return $this->withOrganisation($organisation, 'admin');
    }

    /**
     * Indicate that the user is a team lead.
     *
     * @return static
     */
    public function teamLead(): static
    {
        return $this->afterCreating(function (User $user) {
            // Ensure user has an organisation
            if (!$user->organisation_id) {
                $user = $this->withOrganisation()->afterCreating(function () {})->createOne();
            }

            // Create a team with this user as the team lead
            $team = \App\Models\Team::factory()
                ->withTeamLead($user)
                ->forOrganisation($user->organisation_id)
                ->create();

            // Ensure the user is part of the team
            $team->users()->syncWithoutDetaching([$user->id]);
        });
    }

    /**
     * Indicate that the user is a member of a team.
     *
     * @param \App\Models\Team|int|null $team
     * @return static
     */
    public function withTeam($team = null): static
    {
        return $this->afterCreating(function (User $user) use ($team) {
            if (!$team) {
                // If no team is provided, create a new one
                if (!$user->organisation_id) {
                    $user = $this->withOrganisation()->afterCreating(function () {})->createOne();
                }

                $team = \App\Models\Team::factory()
                    ->forOrganisation($user->organisation_id)
                    ->create();
            }

            $teamId = $team instanceof \App\Models\Team ? $team->id : $team;

            // Attach user to the team
            $user->teams()->syncWithoutDetaching([$teamId]);
        });
    }

    /**
     * Indicate that the user is a member of multiple teams.
     *
     * @param int $count
     * @return static
     */
    public function withTeams(int $count = 2): static
    {
        return $this->afterCreating(function (User $user) use ($count) {
            // Ensure user has an organisation
            if (!$user->organisation_id) {
                $user = $this->withOrganisation()->afterCreating(function () {})->createOne();
            }

            // Create teams belonging to the user's organization
            $teams = \App\Models\Team::factory()
                ->count($count)
                ->forOrganisation($user->organisation_id)
                ->create();

            // Attach user to all teams
            $user->teams()->syncWithoutDetaching($teams->pluck('id')->toArray());
        });
    }

    /**
     * Indicate that the user is assigned to a project.
     *
     * @param \App\Models\Project|int|null $project
     * @return static
     */
    public function withProject($project = null): static
    {
        return $this->afterCreating(function (User $user) use ($project) {
            if (!$project) {
                // If no project is provided, create a new one
                if (!$user->teams()->exists()) {
                    $user = $this->withTeam()->afterCreating(function () {})->createOne();
                }

                $team = $user->teams()->first();
                $project = \App\Models\Project::factory()
                    ->forTeam($team)
                    ->create();
            }

            $projectId = $project instanceof \App\Models\Project ? $project->id : $project;

            // Attach user to the project
            $user->projects()->syncWithoutDetaching([$projectId]);
        });
    }

    /**
     * Indicate that the user is assigned to multiple projects.
     *
     * @param int $count
     * @return static
     */
    public function withProjects(int $count = 2): static
    {
        return $this->afterCreating(function (User $user) use ($count) {
            // Ensure user has a team
            if (!$user->teams()->exists()) {
                $user = $this->withTeam()->afterCreating(function () {})->createOne();
            }

            $team = $user->teams()->first();

            // Create projects belonging to the user's team
            $projects = \App\Models\Project::factory()
                ->count($count)
                ->forTeam($team)
                ->create();

            // Attach user to all projects
            $user->projects()->syncWithoutDetaching($projects->pluck('id')->toArray());
        });
    }

    /**
     * Indicate that the user is authenticated via social login.
     *
     * @param string $provider
     * @return static
     */
    public function withSocialAuth(string $provider = 'github'): static
    {
        return $this->state(function (array $attributes) use ($provider) {
            return [
                'provider' => $provider,
                'provider_id' => Str::random(21),
            ];
        });
    }
}
