<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChangeType;

class ChangeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types=[
          [
              'name'=>'status',
              'description'=>'Change status of a task'
          ],
          [
              'name'=>'priority',
              'description'=>'Change priority of a task'
          ],
            [
                'name'=>'task_type',
                'description'=>'Change type of a task'
            ],
            [
                'name'=>'responsible',
                'description'=>'Change responsible of a task'
            ],
            [
                'name'=>'reporter',
                'description'=>'Change reporter of a task'
            ],
            [
                'name'=>'parent_task',
                'description'=>'Change parent task of a task'
            ],
            [
                'name'=>'board',
                'description'=>'Change board of a task'
            ],
            [
                'name'=>'project',
                'description'=>'Change project of a task'
            ],
        ];

        foreach($types as $type){
            ChangeType::create($type);
        }
    }
}
