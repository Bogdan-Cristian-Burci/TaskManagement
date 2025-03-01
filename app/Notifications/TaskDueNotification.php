<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDueNotification extends Notification implements ShouldQueue
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/tasks/{$this->task->id}");
        $dueDate = $this->task->due_date->format('Y-m-d H:i');
        $hoursLeft = $this->task->due_date->diffInHours(now());

        return (new MailMessage)
            ->subject("Task Due Soon: {$this->task->name}")
            ->line("Task #{$this->task->task_number}: {$this->task->name} is due soon.")
            ->line("Due Date: {$dueDate} ({$hoursLeft} hours remaining)")
            ->action('View Task', $url)
            ->line('Please update the task status or request more time if needed.');
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
            'project_name' => $this->task->project?->name ?? 'Unknown Project',
            'due_date' => $this->task->due_date,
            'hours_remaining' => $this->task->due_date->diffInHours(now()),
            'type' => 'task_due'
        ];
    }
}
