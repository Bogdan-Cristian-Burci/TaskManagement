<?php

namespace App\Providers;

use App\Events\AttachmentCreatedEvent;
use App\Events\AttachmentDeletedEvent;
use App\Listeners\AttachmentCreatedListener;
use App\Listeners\AttachmentDeletedListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        AttachmentCreatedEvent::class => [
            AttachmentCreatedListener::class,
        ],
        AttachmentDeletedEvent::class => [
            AttachmentDeletedListener::class,
        ],
        // ... other event mappings
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
