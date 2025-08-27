<?php

namespace App\Providers;

use App\Listeners\RecordHeartbeatListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Spatie\UptimeMonitor\Events\UptimeCheckFailed;
use Spatie\UptimeMonitor\Events\UptimeCheckSucceeded;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UptimeCheckSucceeded::class => [
            RecordHeartbeatListener::class,
        ],
        UptimeCheckFailed::class => [
            RecordHeartbeatListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
