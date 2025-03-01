<?php

namespace App\Observers;

use App\Models\Task;
use App\Events\TaskCreatedEvent;
use App\Events\TaskUpdatedEvent;
use App\Events\TaskDeletingEvent;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskDueNotification;

class TaskObserver
{
    /**
     * Handle the Task "creating" event.
     */
    public function creating(Task $task): void
    {
        // Auto-increment task number per project (if not set)
        if (!$task->task_number) {
            $maxTaskNumber = Task::where('project_id', $task->project_id)->max('task_number') ?? 0;
            $task->task_number = $maxTaskNumber + 1;
        }
    }

    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        // Dispatch event for real-time updates and logging
        event(new TaskCreatedEvent($task));

        // Notify the responsible user
        if ($task->responsible) {
            $task->responsible->notify(new TaskAssignedNotification($task));
        }

        // Create initial history entry
        $task->history()->create([
            'user_id' => auth()->id(),
            'field_changed' => 'created',
            'new_data' => $task->toArray(),
        ]);
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        // Dispatch event for real-time updates
        event(new TaskUpdatedEvent($task));

        // If responsible user changed, notify the new assignee
        if ($task->isDirty('responsible_id') && $task->responsible) {
            $task->responsible->notify(new TaskAssignedNotification($task));
        }

        // If due date changed or became close, send notification
        if (($task->isDirty('due_date') ||
                ($task->due_date && now()->diffInHours($task->due_date) <= 24)) &&
            $task->responsible) {
            $task->responsible->notify(new TaskDueNotification($task));
        }

        // Track changes in history
        $changes = $task->getDirty();
        $original = $task->getOriginal();

        foreach ($changes as $field => $newValue) {
            $oldValue = $original[$field] ?? null;

            if ($oldValue !== $newValue) {
                $task->history()->create([
                    'user_id' => auth()->id(),
                    'field_changed' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ]);
            }
        }
    }

    /**
     * Handle the Task "deleting" event.
     */
    public function deleting(Task $task): void
    {
        // Dispatch event before deletion
        event(new TaskDeletingEvent($task));

        // Log the deletion in history (will be soft deleted with the task if using SoftDeletes)
        $task->history()->create([
            'user_id' => auth()->id(),
            'field_changed' => 'deleted',
            'old_data' => $task->toArray(),
        ]);
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        // Track task restoration in history
        $task->history()->create([
            'user_id' => auth()->id(),
            'field_changed' => 'restored',
            'new_data' => $task->toArray(),
        ]);
    }
}
