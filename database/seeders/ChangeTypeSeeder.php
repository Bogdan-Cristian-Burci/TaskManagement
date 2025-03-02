<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChangeType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChangeTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing change types
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ChangeType::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clear any cached change types
        Cache::forget('change_types:all');

        $types = [
            [
                'name' => 'status',
                'description' => 'Change status of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'priority',
                'description' => 'Change priority of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'task_type',
                'description' => 'Change type of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'responsible',
                'description' => 'Change responsible of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'reporter',
                'description' => 'Change reporter of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'parent_task',
                'description' => 'Change parent task of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'board',
                'description' => 'Change board of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'project',
                'description' => 'Change project of a task',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Use insert instead of create for better performance with multiple records
        ChangeType::insert($types);

        $this->command->info('ChangeType table seeded successfully!');
    }
}
