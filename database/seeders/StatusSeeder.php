<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'name'=>'To Do',
                'description'=>'This task is yet to be started',
            ],
           [
                'name'=>'In Progress',
                'description'=>'This task is currently being worked on',
            ],
            [
                'name'=>'Done',
                'description'=>'This task has been completed',
            ]
        ];

        foreach($statuses as $status){
            Status::create($status);
        }
    }
}
