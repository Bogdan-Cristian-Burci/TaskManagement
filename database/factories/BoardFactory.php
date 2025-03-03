<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\Project;
use App\Models\BoardType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
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
            'type' => $this->faker->randomElement(['scrum', 'kanban', 'simple']),
            'project_id' => Project::factory(),
            'board_type_id' => BoardType::factory(),
            'is_archived' => false,
        ];
    }

    /**
     * Indicate that the board is archived.
     *
     * @return static
     */
    public function archived(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_archived' => true,
            ];
        });
    }

    /**
     * Indicate that the board belongs to a specific project.
     *
     * @param Project|int $project
     * @return static
     */
    public function forProject($project): static
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        return $this->state(function (array $attributes) use ($projectId) {
            return [
                'project_id' => $projectId,
            ];
        });
    }

    /**
     * Indicate that the board is of a specific board type.
     *
     * @param BoardType|int $boardType
     * @return static
     */
    public function ofType($boardType): static
    {
        $boardTypeId = $boardType instanceof BoardType ? $boardType->id : $boardType;

        return $this->state(function (array $attributes) use ($boardTypeId) {
            return [
                'board_type_id' => $boardTypeId,
            ];
        });
    }

    /**
     * Indicate that the board should have columns.
     *
     * @param int $count
     * @return static
     */
    public function withColumns(int $count = 3): static
    {
        return $this->afterCreating(function (Board $board) use ($count) {
            $defaultColumns = ['To Do', 'In Progress', 'Done'];

            for ($i = 0; $i < $count; $i++) {
                $name = $i < 3 ? $defaultColumns[$i] : $this->faker->word;
                $board->columns()->create([
                    'name' => $name,
                    'position' => $i + 1
                ]);
            }
        });
    }
}
