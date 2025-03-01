<?php

namespace App\Observers;

use App\Models\BoardColumn;
use App\Events\BoardColumnCreatedEvent;
use App\Events\BoardColumnUpdatedEvent;
use App\Events\BoardColumnDeletingEvent;

class BoardColumnObserver
{
    /**
     * Handle the BoardColumn "created" event.
     */
    public function created(BoardColumn $boardColumn): void
    {
        // Dispatch event for real-time updates
        event(new BoardColumnCreatedEvent($boardColumn));

        //Log activity
        activity()
            ->performedOn($boardColumn)
            ->causedBy(auth()->user())
            ->withProperties([
                'attributes' => $boardColumn->toArray(),
            ])
            ->log('created');
    }

    /**
     * Handle the BoardColumn "updated" event.
     */
    public function updated(BoardColumn $boardColumn): void
    {
        // Dispatch event for real-time updates
        event(new BoardColumnUpdatedEvent($boardColumn));

        // Track changes in history
        $changes = $boardColumn->getDirty();
        $original = $boardColumn->getOriginal();

        foreach ($changes as $field => $newValue) {
            $oldValue = $original[$field] ?? null;

            if ($oldValue !== $newValue) {
                activity()
                    ->performedOn($boardColumn)
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
     * Handle the BoardColumn "deleting" event.
     */
    public function deleting(BoardColumn $boardColumn): void
    {
        // Dispatch event before deletion
        event(new BoardColumnDeletingEvent($boardColumn));

        // Log activity
        activity()
            ->performedOn($boardColumn)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $boardColumn->toArray()])
            ->log('deleted');
    }
}
