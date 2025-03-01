<?php

namespace App\Observers;

use App\Models\Board;
use App\Events\BoardCreatedEvent;
use App\Events\BoardUpdatedEvent;
use App\Events\BoardDeletingEvent;

class BoardObserver
{
    /**
     * Handle the Board "created" event.
     */
    public function created(Board $board): void
    {
        // Dispatch event for real-time updates
        event(new BoardCreatedEvent($board));

        activity()
            ->performedOn($board)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $board->toArray()])
            ->log('created');

    }

    /**
     * Handle the Board "updated" event.
     */
    public function updated(Board $board): void
    {
        // Dispatch event for real-time updates
        event(new BoardUpdatedEvent($board));

        // Track changes in history if you have a BoardHistory model
        $changes = $board->getDirty();
        $original = $board->getOriginal();

        foreach ($changes as $field => $newValue) {
            $oldValue = $original[$field] ?? null;

            if ($oldValue !== $newValue) {
                activity()
                    ->performedOn($board)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'attributes' => [$field => $newValue],
                        'old' => [$field => $oldValue]
                    ])
                    ->log('updated');
            }
        }
    }

    /**
     * Handle the Board "deleting" event.
     */
    public function deleting(Board $board): void
    {
        // Dispatch event before deletion
        event(new BoardDeletingEvent($board));

        activity()
            ->performedOn($board)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $board->toArray()])
            ->log('deleted');

    }
}
