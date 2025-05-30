<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ChangeTypeSeeder::class,
            PrioritySeeder::class,
            TaskTypeSeeder::class,
            WorkflowSeeder::class,
            BoardTypeSeeder::class,
            SystemTagsSeeder::class,
        ]);
    }
}
