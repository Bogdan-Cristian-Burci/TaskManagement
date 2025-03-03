<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'organisation_id' => Organisation::factory(),
            'team_id' => Team::factory(),
            'status' => $this->faker->randomElement(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'start_date' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'end_date' => $this->faker->dateTimeBetween('next week', '+6 months'),
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            // Generate a key for the project if not set
            if (!$project->key) {
                $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $project->name), 0, 3));
                $project->key = $prefix . '-' . $project->id;
                $project->save();
            }
        });
    }

    /**
     * Indicate that the project is active.
     *
     * @return static
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'start_date' => now()->subDays(rand(1, 30)),
                'end_date' => now()->addDays(rand(30, 90)),
            ];
        });
    }

    /**
     * Indicate that the project is completed.
     *
     * @return static
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = now()->subDays(rand(60, 180));
            return [
                'status' => 'completed',
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addDays(rand(30, 60)),
            ];
        });
    }

    /**
     * Indicate that the project is for a specific organisation.
     *
     * @param Organisation|int $organisation
     * @return static
     */
    public function forOrganisation($organisation): static
    {
        $organisationId = $organisation instanceof Organisation ? $organisation->id : $organisation;

        return $this->state(function (array $attributes) use ($organisationId) {
            return [
                'organisation_id' => $organisationId,
            ];
        });
    }

    /**
     * Indicate that the project is for a specific team.
     *
     * @param Team|int $team
     * @return static
     */
    public function forTeam($team): static
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        return $this->state(function (array $attributes) use ($teamId) {
            // Get the team to ensure we assign the correct organisation
            $team = Team::find($teamId);

            return [
                'team_id' => $teamId,
                'organisation_id' => $team ? $team->organisation_id : $attributes['organisation_id'],
            ];
        });
    }

    /**
     * Indicate that the project should have users.
     *
     * @param array|int $users The user IDs or User models to attach
     * @param string $role The role to assign to users
     * @return static
     */
    public function withUsers($users, string $role = 'member'): static
    {
        return $this->afterCreating(function (Project $project) use ($users, $role) {
            $userIds = collect($users)->map(function ($user) {
                return $user instanceof User ? $user->id : $user;
            })->toArray();

            $pivotData = [];
            foreach ($userIds as $userId) {
                $pivotData[$userId] = ['role' => $role];
            }

            $project->users()->syncWithoutDetaching($pivotData);
        });
    }

    /**
     * Indicate that the project has a project manager.
     *
     * @param User|int|null $user
     * @return static
     */
    public function withManager($user = null): static
    {
        return $this->afterCreating(function (Project $project) use ($user) {
            if (!$user) {
                // Create a user in the same organisation as the project
                $user = User::factory()->create([
                    'organisation_id' => $project->organisation_id,
                ]);
            }

            $userId = $user instanceof User ? $user->id : $user;

            $project->users()->syncWithoutDetaching([
                $userId => ['role' => 'manager']
            ]);
        });
    }

    /**
     * Indicate that the project should have boards.
     *
     * @param int $count
     * @return static
     */
    public function withBoards(int $count = 1): static
    {
        return $this->afterCreating(function (Project $project) use ($count) {
            \App\Models\Board::factory()
                ->count($count)
                ->for($project)
                ->create();
        });
    }

    /**
     * Indicate that the project should have a default board.
     *
     * @return static
     */
    public function withDefaultBoard(): static
    {
        return $this->afterCreating(function (Project $project) {
            \App\Models\Board::factory()
                ->for($project)
                ->create([
                    'name' => 'Default Board',
                    'is_default' => true,
                    'description' => 'Default board for ' . $project->name,
                ]);
        });
    }

    /**
     * Indicate that the project should have tasks.
     *
     * @param int $count
     * @return static
     */
    public function withTasks(int $count = 5): static
    {
        return $this->afterCreating(function (Project $project) use ($count) {
            // First ensure the project has a board
            $board = $project->boards()->first();
            if (!$board) {
                $board = \App\Models\Board::factory()
                    ->for($project)
                    ->create(['is_default' => true]);
            }

            // Create columns if the board doesn't have any
            if ($board->columns()->count() === 0) {
                $columns = [
                    ['name' => 'Todo', 'order' => 1],
                    ['name' => 'In Progress', 'order' => 2],
                    ['name' => 'Done', 'order' => 3],
                ];

                foreach ($columns as $columnData) {
                    $board->columns()->create($columnData);
                }
            }

            $columns = $board->columns()->pluck('id')->toArray();

            // Create tasks
            \App\Models\Task::factory()
                ->count($count)
                ->for($project)
                ->recycle($board)
                ->state(function (array $attributes) use ($columns) {
                    return [
                        'column_id' => $columns[array_rand($columns)],
                    ];
                })
                ->create();
        });
    }

    /**
     * Indicate that the project is overdue.
     *
     * @return static
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'start_date' => now()->subDays(rand(60, 90)),
                'end_date' => now()->subDays(rand(1, 15)),
            ];
        });
    }

    /**
     * Indicate that the project has tags.
     *
     * @param int $count
     * @return static
     */
    public function withTags(int $count = 3): static
    {
        return $this->afterCreating(function (Project $project) use ($count) {
            \App\Models\Tag::factory()
                ->count($count)
                ->for($project)
                ->create();
        });
    }
}
