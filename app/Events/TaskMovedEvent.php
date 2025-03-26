<?php

namespace App\Events;

use App\Models\Task;
use App\Models\BoardColumn;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskMovedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public BoardColumn $fromColumn,
        public BoardColumn $toColumn
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('board.' . $this->task->board_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.moved';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'from_column_id' => $this->fromColumn->id,
            'to_column_id' => $this->toColumn->id,
            'board_id' => $this->task->board_id,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
