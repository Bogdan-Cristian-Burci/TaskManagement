<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BoardType;

class BoardTypeSeeder extends Seeder
{
    public function run(): void
    {
        $boards = [
            ['name' => 'Kanban', 'description' => 'Kanban board for visualizing workflow'],
            ['name' => 'Scrum', 'description' => 'Agile scrum board with sprints'],
            ['name' => 'Bug', 'description' => 'Timeline view of tasks'],
        ];

        foreach ($boards as $board) {
            BoardType::create($board);
        }
    }
}
