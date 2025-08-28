<?php

namespace App\Jobs;

use App\Services\HeartbeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Spatie\UptimeMonitor\Models\Monitor;

class CheckMonitorBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $monitorIds)
    {
        $this->onQueue('monitor');
    }

    public function handle(HeartbeatService $heartbeatService)
    {
        $monitors = Monitor::whereIn('id', $this->monitorIds)->get();

        $client = new Client(['timeout' => 10]);

        $promises = [];

        foreach ($monitors as $monitor) {
            $promises[$monitor->id] = $client->getAsync($monitor->url);
        }

        $results = Promise\Utils::settle($promises)->wait();
        $startTime = microtime(true);
        foreach ($monitors as $monitor) {
            $result = $results[$monitor->id];

            if ($result['state'] === 'fulfilled') {
                $response = $result['value'];
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                $monitor->uptimeRequestSucceeded($response);
                $heartbeatService->recordHeartbeat(
                    monitor: $monitor,
                    status: 'up',
                    responseTime: $responseTime,
                    statusCode: $response->getStatusCode(),
                    checkMethod: $monitor->uptime_check_method ?? 'GET'
                );
            } else {
                $monitor->uptimeRequestFailed(isset($result['reason']) ? $result['reason'] : 'Unknown error');
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
