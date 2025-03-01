<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaskType;

class TaskTypeSeeder extends Seeder
{
    public function run(): void
    {
        $taskTypes=[
            [
                'name'=>'Task',
                'description'=>'A task is a piece of work that needs to be done.'
            ],
            [
                'name'=>'Bug',
                'description'=>'A bug is a defect in the software that needs to be fixed.'
            ],
            [
                'name'=>'Feature',
                'description'=>'A feature is a new functionality that needs to be added.'
            ],
            [
                'name'=>'Improvement',
                'description'=>'An improvement is an enhancement to an existing functionality.'
            ]
        ];

        foreach($taskTypes as $taskType){
            TaskType::create($taskType);
        }
    }
}
