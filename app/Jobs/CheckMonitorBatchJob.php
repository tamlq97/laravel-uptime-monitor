<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\HeartbeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CheckMonitorBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $monitorIds)
    {
        $this->onQueue('monitor');
    }

    public function handle(HeartbeatService $heartbeatService)
    {
        /** @var Collection<int,Monitor> $monitors */
        $monitors = Monitor::whereIn('id', $this->monitorIds)->get();

        $pool = Http::pool(function ($pool) use ($monitors) {
            $requests = [];
            foreach ($monitors as $monitor) {
                $requests[$monitor->id] = $pool->timeout(10)->get($monitor->url);
            }
            return $requests;
        });

        $startTime = microtime(true);

        foreach ($monitors as $monitor) {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $pool[$monitor->id];

            if ($response->successful()) {
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                $monitor->uptimeRequestSucceeded($response);
                $heartbeatService->recordHeartbeat(
                    monitor: $monitor,
                    status: 'up',
                    responseTime: $responseTime,
                    statusCode: $response->status(),
                    checkMethod: $monitor->uptime_check_method ?? 'GET'
                );
            } else {
                $errorMessage = $response->failed()
                    ? "HTTP {$response->status()}: {$response->body()}"
                    : 'Request failed';

                $monitor->uptimeRequestFailed($errorMessage);
                $heartbeatService->recordHeartbeat(
                    monitor: $monitor,
                    status: 'down',
                    errorMessage: $monitor->uptime_check_failure_reason,
                    checkMethod: $monitor->uptime_check_method ?? 'GET'
                );
            }
        }
    }
}
