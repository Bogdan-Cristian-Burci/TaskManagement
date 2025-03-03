<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\Sprint;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::now()->addDays(rand(-30, 30));
        $endDate = (clone $startDate)->addDays(rand(7, 14));

        return [
            'name' => 'Sprint ' . $this->faker->word,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'board_id' => Board::factory(),
            'goal' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['planning', 'active', 'completed']),
        ];
    }

    /**
     * Indicate that the sprint is in planning.
     *
     * @return static
     */
    public function planning(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'planning',
                'start_date' => Carbon::now()->addDays(7),
            ];
        });
    }

    /**
     * Indicate that the sprint is active.
     *
     * @return static
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'start_date' => Carbon::now()->subDays(3),
                'end_date' => Carbon::now()->addDays(11),
            ];
        });
    }

    /**
     * Indicate that the sprint is completed.
     *
     * @return static
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = Carbon::now()->subDays(rand(30, 60));
            return [
                'status' => 'completed',
                'start_date' => $startDate,
                'end_date' => (clone $startDate)->addDays(14),
            ];
        });
    }

    /**
     * Indicate that the sprint belongs to a specific board.
     *
     * @param Board|int $board
     * @return static
     */
    public function forBoard($board): static
    {
        $boardId = $board instanceof Board ? $board->id : $board;

        return $this->state(function (array $attributes) use ($boardId) {
            return [
                'board_id' => $boardId,
            ];
        });
    }

    /**
     * Indicate that the sprint should have tasks.
     *
     * @param int $count
     * @return static
     */
    public function withTasks(int $count = 5): static
    {
        return $this->afterCreating(function (Sprint $sprint) use ($count) {
            // Get the project ID from the board
            $projectId = $sprint->board->project_id;

            // Create tasks for this project
            $tasks = \App\Models\Task::factory()
                ->count($count)
                ->for(\App\Models\Project::find($projectId))
                ->create();

            // Attach tasks to the sprint
            $sprint->tasks()->attach($tasks->pluck('id'));
        });
    }
}
