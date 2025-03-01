<?php

namespace App\Observers;

use App\Models\BoardType;
use App\Events\BoardTypeCreatedEvent;
use App\Events\BoardTypeUpdatedEvent;
use App\Events\BoardTypeDeletingEvent;

class BoardTypeObserver
{
    /**
     * Handle the BoardType "created" event.
     */
    public function created(BoardType $boardType): void
    {
        // Dispatch event for real-time upd
        event(new BoardTypeCreatedEvent($boardType));

        //Log activity
        activity()
            ->performedOn($boardType)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $boardType->toArray()])
            ->log('created');
    }

    /**
     * Handle the BoardType "updated" event.
     */
    public function updated(BoardType $boardType): void
    {
        // Dispatch event for real-time updates
        event(new BoardTypeUpdatedEvent($boardType));

        // Track changes in history
        $changes = $boardType->getDirty();
        $original = $boardType->getOriginal();

        foreach ($changes as $field => $newValue) {
            $oldValue = $original[$field] ?? null;

            if ($oldValue !== $newValue) {
                activity()
                    ->performedOn($boardType)
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
     * Handle the BoardType "deleting" event.
     */
    public function deleting(BoardType $boardType): void
    {
        // Dispatch event before deletion
        event(new BoardTypeDeletingEvent($boardType));

        // Log activity
        activity()
            ->performedOn($boardType)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $boardType->toArray()])
            ->log('deleted');
    }

    /**
     * Handle the BoardType "restored" event.
     */
    public function restored(BoardType $boardType): void
    {
        activity()
            ->performedOn($boardType)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $boardType->toArray()])
            ->log('restored');
    }
}
