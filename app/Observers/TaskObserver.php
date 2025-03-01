<?php

namespace App\Observers;

use App\Models\Task;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskDueNotification;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Notify responsible user about new task assignment
        if ($task->responsible) {
            $task->responsible->notify(new TaskAssignedNotification($task));
        }
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // If responsible changed, notify the new assignee
        if ($task->isDirty('responsible_id') && $task->responsible) {
            $task->responsible->notify(new TaskAssignedNotification($task));
        }

        // If due date changed and is within 24 hours, send due date notification
        if ($task->isDirty('due_date') &&
            $task->due_date &&
            $task->due_date->diffInHours(now()) <= 24) {
            $task->responsible->notify(new TaskDueNotification($task));
        }
    }
}
