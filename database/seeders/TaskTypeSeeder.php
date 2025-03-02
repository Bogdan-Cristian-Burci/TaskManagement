<?php

namespace Database\Seeders;

use App\Models\TaskType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TaskTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Clear existing task types
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        TaskType::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clear any cached task types
        Cache::forget('task_types:all');
        Cache::forget('task_types:with_tasks_count');

        // Define task types with proper attributes
        $taskTypes = [
            [
                'name' => 'Feature',
                'description' => 'New functionality to be developed',
                'icon' => 'star',
                'color' => '#3498db', // Blue
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bug',
                'description' => 'Something that needs to be fixed',
                'icon' => 'bug',
                'color' => '#e74c3c', // Red
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enhancement',
                'description' => 'Improvement to existing functionality',
                'icon' => 'arrow-up',
                'color' => '#2ecc71', // Green
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Documentation',
                'description' => 'Creating or updating documentation',
                'icon' => 'book',
                'color' => '#9b59b6', // Purple
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Research',
                'description' => 'Investigation tasks that require analysis',
                'icon' => 'search',
                'color' => '#f39c12', // Orange
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Testing',
                'description' => 'Testing related tasks',
                'icon' => 'vial',
                'color' => '#1abc9c', // Turquoise
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert all task types
        TaskType::insert($taskTypes);

        $this->command->info('TaskType table seeded successfully!');
    }
}
