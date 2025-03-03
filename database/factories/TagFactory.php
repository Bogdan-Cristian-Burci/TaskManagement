<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $colorPrefix = '#';
        $colorValue = $this->faker->hexColor;

        // Remove the # prefix if it exists (faker sometimes includes it)
        $colorValue = ltrim($colorValue, '#');

        return [
            'name' => $this->faker->unique()->word(),
            'color' => $colorPrefix . $colorValue,
            'project_id' => Project::factory(),
        ];
    }

    /**
     * Indicate that the tag should have a specific color.
     *
     * @param string $color Hex color code with or without # prefix
     * @return static
     */
    public function withColor(string $color): static
    {
        return $this->state(function (array $attributes) use ($color) {
            // Ensure color has # prefix
            if (!str_starts_with($color, '#')) {
                $color = "#{$color}";
            }

            return [
                'color' => $color,
            ];
        });
    }

    /**
     * Indicate that the tag belongs to a specific project.
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
     * Create multiple tags with standard color presets.
     *
     * @param Project|int $project
     * @return array
     */
    public static function createDefaultSet($project): array
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        $defaultTags = [
            ['name' => 'Bug', 'color' => '#e74c3c'],  // Red
            ['name' => 'Feature', 'color' => '#3498db'], // Blue
            ['name' => 'Enhancement', 'color' => '#2ecc71'], // Green
            ['name' => 'Documentation', 'color' => '#f1c40f'], // Yellow
            ['name' => 'Question', 'color' => '#9b59b6'], // Purple
        ];

        $tags = [];

        foreach ($defaultTags as $tagData) {
            $tags[] = Tag::factory()->create([
                'name' => $tagData['name'],
                'color' => $tagData['color'],
                'project_id' => $projectId,
            ]);
        }

        return $tags;
    }
}
