<?php

namespace App\Jobs;

use App\Services\HeartbeatService;
use Generator;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\EachPromise;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Http\Message\ResponseInterface;
use Spatie\UptimeMonitor\Models\Monitor;

class CheckUptimeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Monitor $monitor)
    {
        $this->onQueue('monitor');
    }

    public function handle(HeartbeatService $heartbeatService): void
    {
        (new EachPromise($this->getPromises(), [
            'concurrency' => config('uptime-monitor.uptime_check.concurrent_checks'),
            'fulfilled' => function (ResponseInterface $response, $index) use ($heartbeatService) {
                $this->monitor->uptimeRequestSucceeded($response);
                $heartbeatService->recordHeartbeat(
                    monitor: $this->monitor,
                    status: 'up',
                    responseTime: null,
                    statusCode: null,
                    checkMethod: $monitor->uptime_check_method ?? 'GET'
                );
            },

            'rejected' => function (TransferException $exception, $index) use ($heartbeatService) {
                $this->monitor->uptimeRequestFailed($exception->getMessage());
                $heartbeatService->recordHeartbeat(
                    monitor: $this->monitor,
                    status: 'down',
                    errorMessage: $this->monitor->uptime_check_failure_reason,
                    checkMethod: $this->monitor->uptime_check_method ?? 'GET'
                );
            },
        ]))->promise()->wait();
    }

    protected function getPromises(): Generator
    {
        $client = GuzzleFactory::make(
            config('uptime-monitor.uptime_check.guzzle_options', []),
            config('uptime-monitor.uptime-check.retry_connection_after_milliseconds', 100)
        );

        $promise = $client->requestAsync(
            $this->monitor->uptime_check_method,
            $this->monitor->url,
            array_filter([
                'connect_timeout' => config('uptime-monitor.uptime_check.timeout_per_site'),
                'headers' => $this->promiseHeaders($this->monitor),
                'body' => $this->monitor->uptime_check_payload,
            ])
        )->then(
            function (ResponseInterface $response) {
                return $response;
            },
            function (TransferException $exception) {
                if (in_array($exception->getCode(), config('uptime-monitor.uptime_check.additional_status_codes', []))) {
                    return $exception->getResponse();
                }

                throw $exception;
            }
        );

        yield $promise;
    }

    private function promiseHeaders(Monitor $monitor): array
    {
        return collect([])
            ->merge(['User-Agent' => config('uptime-monitor.uptime_check.user_agent')])
            ->merge(config('uptime-monitor.uptime_check.additional_headers') ?? [])
            ->merge($monitor->uptime_check_additional_headers)
            ->toArray();
    }
}
