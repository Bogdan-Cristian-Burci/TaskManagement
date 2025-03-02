<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Clear existing statuses
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Status::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clear any cached statuses
        Cache::forget('statuses:all');
        Cache::forget('statuses:default');

        $statuses = [
            [
                'name' => 'To Do',
                'description' => 'Tasks that are ready to be worked on',
                'color' => '#3498db', // Blue
                'icon' => 'clipboard-list',
                'is_default' => true,
                'position' => 1,
                'category' => 'todo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'In Progress',
                'description' => 'Tasks currently being worked on',
                'color' => '#f39c12', // Orange
                'icon' => 'spinner',
                'is_default' => false,
                'position' => 2,
                'category' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'In Review',
                'description' => 'Tasks that are being reviewed',
                'color' => '#9b59b6', // Purple
                'icon' => 'search',
                'is_default' => false,
                'position' => 3,
                'category' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Done',
                'description' => 'Tasks that have been completed',
                'color' => '#2ecc71', // Green
                'icon' => 'check-circle',
                'is_default' => false,
                'position' => 4,
                'category' => 'done',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Blocked',
                'description' => 'Tasks that are blocked by external factors',
                'color' => '#e74c3c', // Red
                'icon' => 'ban',
                'is_default' => false,
                'position' => 5,
                'category' => 'todo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Canceled',
                'description' => 'Tasks that are no longer necessary',
                'color' => '#95a5a6', // Gray
                'icon' => 'times-circle',
                'is_default' => false,
                'position' => 6,
                'category' => 'canceled',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert all statuses
        Status::insert($statuses);

        $this->command->info('Status table seeded successfully!');
    }
}
