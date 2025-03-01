<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Priority;

class PrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities=[
            [
                'name' => 'Low',
                'value' => '1',
                'color' => '#28a745', // Green
                'position' => 10
            ],
            [
                'name' => 'Medium',
                'value' => '2',
                'color' => '#ffc107', // Yellow
                'position' => 20
            ],
            [
                'name' => 'High',
                'value' => '3',
                'color' => '#fd7e14', // Orange
                'position' => 30
            ],
            [
                'name' => 'Urgent',
                'value' => '4',
                'color' => '#dc3545', // Red
                'position' => 40
            ]
        ];

        foreach($priorities as $priority){
            Priority::create($priority);
        }
    }
}
