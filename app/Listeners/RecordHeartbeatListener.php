<?php

namespace App\Listeners;

use App\Services\HeartbeatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\UptimeMonitor\Events\UptimeCheckFailed;
use Spatie\UptimeMonitor\Events\UptimeCheckSucceeded;
use Illuminate\Queue\InteractsWithQueue;

class RecordHeartbeatListener implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'monitor';
    public function __construct(
        private HeartbeatService $heartbeatService
    ) {}

    public function handle($event)
    {
        logger('RecordHeartbeatListener: received event ' . get_class($event));

        if ($event instanceof UptimeCheckSucceeded) {
            $this->handleUptimeCheckSucceeded($event);
        }

        if ($event instanceof UptimeCheckFailed) {
            $this->handleUptimeCheckFailed($event);
        }
    }

    /**
     * Handle uptime check succeeded event.
     */
    public function handleUptimeCheckSucceeded(UptimeCheckSucceeded $event): void
    {
        $monitor = $event->monitor;

        $this->heartbeatService->recordHeartbeat(
            monitor: $monitor,
            status: 'up',
            responseTime: $this->getResponseTime($event),
            statusCode: $this->getStatusCode($event),
            checkMethod: $monitor->uptime_check_method ?? 'GET'
        );
    }

    /**
     * Handle uptime check failed event.
     */
    public function handleUptimeCheckFailed(UptimeCheckFailed $event): void
    {
        $monitor = $event->monitor;

        $this->heartbeatService->recordHeartbeat(
            monitor: $monitor,
            status: 'down',
            errorMessage: $monitor->uptime_check_failure_reason,
            checkMethod: $monitor->uptime_check_method ?? 'GET'
        );
    }

    private function getResponseTime($event): ?int
    {
        // Extract response time from event if available
        return null; // Implement based on actual event structure
    }

    private function getStatusCode($event): ?int
    {
        // Extract status code from event if available
        return null; // Implement based on actual event structure
    }
}
