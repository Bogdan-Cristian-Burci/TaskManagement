<?php

namespace Database\Seeders;

use App\Models\Priority;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Clear existing priorities
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Priority::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clear any cached priorities
        Cache::forget('priorities:all');

        // Define priorities with proper attributes
        $priorities = [
            [
                'name' => 'Critical',
                'description' => 'Must be done immediately',
                'color' => '#e74c3c', // Red
                'icon' => 'exclamation-triangle',
                'level' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'High',
                'description' => 'Important task that should be done soon',
                'color' => '#f39c12', // Orange
                'icon' => 'arrow-up',
                'level' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Medium',
                'description' => 'Standard priority task',
                'color' => '#3498db', // Blue
                'icon' => 'minus',
                'level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Low',
                'description' => 'Can be completed when time permits',
                'color' => '#2ecc71', // Green
                'icon' => 'arrow-down',
                'level' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'No Priority',
                'description' => 'Unclassified tasks without urgency',
                'color' => '#95a5a6', // Gray
                'icon' => 'ban',
                'level' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert all priorities
        Priority::insert($priorities);

        $this->command->info('Priority table seeded successfully!');
    }
}
