<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\HeartbeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CheckMonitorBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $monitorIds) {}

    public function handle(HeartbeatService $heartbeatService)
    {
        /** @var Collection<int,Monitor> $monitors */
        $monitors = Monitor::whereIn('id', $this->monitorIds)->get();

        // Bắt đầu timer
        $startTime = microtime(true);

        // Gửi pool request
        $responses = Http::pool(function ($pool) use ($monitors) {
            foreach ($monitors as $monitor) {
                $pool->as((string) $monitor->id)->timeout(10)->get($monitor->url);
            }
        });

        // Xử lý kết quả
        foreach ($monitors as $monitor) {
            $response = $responses[$monitor->id];
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($response instanceof Response) {
                if ($response->successful()) {
                    $monitor->uptimeRequestSucceeded($response->toPsrResponse());
                    $heartbeatService->recordHeartbeat(
                        monitor: $monitor,
                        status: 'up',
                        responseTime: $responseTime,
                        statusCode: $response->status(),
                        checkMethod: $monitor->uptime_check_method ?? 'GET'
                    );
                } else {
                    $monitor->uptimeRequestFailed($response->getReasonPhrase() ?: 'Unknown error');
                    $heartbeatService->recordHeartbeat(
                        monitor: $monitor,
                        status: 'down',
                        errorMessage: $monitor->uptime_check_failure_reason,
                        checkMethod: $monitor->uptime_check_method ?? 'GET'
                    );
                }
            } elseif ($response instanceof ConnectionException) {
                // Trường hợp lỗi kết nối (timeout, refused …)
                $monitor->uptimeRequestFailed($response->getMessage());
                $heartbeatService->recordHeartbeat(
                    monitor: $monitor,
                    status: 'down',
                    errorMessage: $monitor->uptime_check_failure_reason,
                    checkMethod: $monitor->uptime_check_method ?? 'GET'
                );
            } else {
                // fallback unknown
                $monitor->uptimeRequestFailed('Unknown error');
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
