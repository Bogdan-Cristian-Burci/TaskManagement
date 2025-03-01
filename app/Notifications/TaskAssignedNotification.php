<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Task $task)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database','broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/tasks/{$this->task->id}");
        $projectName = $this->task->project?->name ?? 'Unknown Project';
        $priorityName = $this->task->priority?->name ?? 'Not set';
        $taskTypeName = $this->task->taskType?->name ?? 'Not set';

        return (new MailMessage)
            ->subject("Task Assigned: #{$this->task->task_number} - {$this->task->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned to a task in the project: {$projectName}")
            ->line("**Task Details:**")
            ->line("- **Number:** #{$this->task->task_number}")
            ->line("- **Name:** {$this->task->name}")
            ->line("- **Priority:** {$priorityName}")
            ->line("- **Type:** {$taskTypeName}")
            ->when($this->task->due_date, function ($mail) {
                return $mail->line("- **Due Date:** {$this->task->due_date->format('Y-m-d H:i')}");
            })
            ->action('View Task', $url)
            ->line('Please review the task details and update the status as you progress.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'name' => $this->task->name,
            'project_id' => $this->task->project_id,
            'project_name' => $this->task->project?->name,
            'priority_id' => $this->task->priority_id,
            'priority_name' => $this->task->priority?->name,
            'due_date' => $this->task->due_date,
            'reporter_id' => $this->task->reporter_id,
            'reporter_name' => $this->task->reporter?->name,
            'created_at' => now(),
            'type' => 'task_assigned',
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'name' => $this->task->name,
            'project_name' => $this->task->project?->name,
            'type' => 'task_assigned',
            'time' => now()->diffForHumans(),
        ]);
    }
}
