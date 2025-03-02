<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => $this->faker->paragraph(),
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
        ];
    }

    /**
     * Create a comment that is a reply to another comment.
     *
     * @return Factory
     */
    public function asReply(): Factory
    {
        return $this->state(function (array $attributes) {
            // First, create a parent comment if one wasn't provided
            $parentComment = Comment::factory()->create([
                'task_id' => $attributes['task_id'] ?? Task::factory(),
            ]);

            return [
                'parent_id' => $parentComment->id,
                'task_id' => $parentComment->task_id,
            ];
        });
    }
}
